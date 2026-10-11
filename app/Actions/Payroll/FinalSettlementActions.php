<?php

declare(strict_types=1);

namespace App\Actions\Payroll;

use App\Enums\EmploymentType;
use App\Models\Advance;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\FinalSettlement;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\PfLedgerEntry;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRulesFactory;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use App\Services\Payroll\GratuityCalculator;
use App\Services\Payroll\PayslipCalculator;
use App\Services\Settings\TenantSettings;
use App\Support\Brand;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Full and final settlement (feature payroll_settlement): salary for the unpaid days of the last month, leave
 * encashment, gratuity, provident fund balance, minus outstanding advances. Draft → HR may add lines → final.
 */
final class FinalSettlementActions
{
    public function __construct(
        private readonly TenantSettings $settings,
        private readonly PayCycle $cycle,
        private readonly PeriodRulesFactory $rules,
        private readonly LeaveBalanceService $balances,
        private readonly LeaveYear $leaveYear,
        private readonly GratuityCalculator $gratuity,
    ) {}

    public function calculate(Employee $employee, string $lastDay, User $by): FinalSettlement
    {
        $this->assert($by->can('payroll.manage') && $this->settings->featureEnabled('payroll_settlement'), __('You cannot prepare settlements.'));
        $existing = FinalSettlement::query()->where('employee_id', $employee->id)->first();
        $this->assert($existing?->status !== 'final', __('This employee\'s settlement is already final.'));
        $structure = SalaryStructure::effectiveFor($employee->id, $lastDay);
        $this->assert($structure !== null, __('Add a salary structure for this employee first.'));

        $perDay = $this->perDay($structure, $lastDay);
        $lines = [...$this->lastSalary($employee, $structure, $lastDay, $perDay), ...$this->leaveEncashment($employee, $lastDay, $perDay)];
        if ($this->settings->featureEnabled('payroll_gratuity')) {
            $g = $this->gratuity->accrued($employee, $lastDay);
            if ($g['amount'] > 0) {
                $lines[] = ['label' => __('Gratuity (:y years × :d days)', ['y' => $g['years'], 'd' => $this->settings->get('gratuity_days_per_year', 30)]),
                    'type' => 'earning', 'amount' => $g['amount'], 'code' => 'gratuity'];
            }
        }
        if ($this->settings->featureEnabled('payroll_pf') && $employee->pf_member) {
            $pf = PfLedgerEntry::balance($employee->id, $lastDay);
            if ($pf['total'] > 0) {
                $lines[] = ['label' => __('Provident fund balance (your :e + company :c)', ['e' => number_format($pf['employee']), 'c' => number_format($pf['employer'])]),
                    'type' => 'earning', 'amount' => $pf['total'], 'code' => 'pf'];
            }
        }
        $owed = (int) Advance::query()->where('employee_id', $employee->id)->where('status', 'active')->sum('remaining');
        if ($owed > 0) {
            $lines[] = ['label' => __('Outstanding advances / loans'), 'type' => 'deduction', 'amount' => $owed, 'code' => 'advance'];
        }
        // Lines HR added by hand on the draft are kept.
        $manual = array_values(array_filter($existing->lines ?? [], fn (array $l) => ($l['code'] ?? '') === 'manual'));

        $settlement = $existing ?? new FinalSettlement(['employee_id' => $employee->id, 'created_by' => $by->id]);
        $settlement->fill(['last_working_day' => $lastDay, 'lines' => [...$lines, ...$manual]]);
        $settlement->net = self::net($settlement->lines);
        $settlement->save();

        return $settlement;
    }

    /** @param list<array{label: string, type: string, amount: int|string}> $manual lines HR typed in */
    public function updateManualLines(FinalSettlement $settlement, array $manual): FinalSettlement
    {
        $this->assert($settlement->status === 'draft', __('A final settlement cannot be changed.'));
        $auto = array_values(array_filter($settlement->lines, fn (array $l) => ($l['code'] ?? '') !== 'manual'));
        $typed = array_map(fn (array $l) => ['label' => (string) $l['label'], 'type' => $l['type'] === 'deduction' ? 'deduction' : 'earning',
            'amount' => max(0, (int) $l['amount']), 'code' => 'manual'], $manual);
        $settlement->lines = [...$auto, ...$typed];
        $settlement->net = self::net($settlement->lines);
        $settlement->save();

        return $settlement;
    }

    public function finalise(FinalSettlement $settlement, User $by): FinalSettlement
    {
        $this->assert($by->can('payroll.manage'), __('You cannot finalise settlements.'));
        $this->assert($settlement->status === 'draft', __('This settlement is already final.'));

        return DB::transaction(function () use ($settlement, $by) {
            $lastDay = $settlement->last_working_day->format('Y-m-d');
            foreach ($settlement->lines as $line) {
                if (($line['code'] ?? '') === 'advance') {
                    Advance::query()->where('employee_id', $settlement->employee_id)->where('status', 'active')->update(['remaining' => 0, 'status' => 'settled']);
                }
                if (($line['code'] ?? '') === 'pf') {
                    $pf = PfLedgerEntry::balance($settlement->employee_id, $lastDay);
                    PfLedgerEntry::create(['employee_id' => $settlement->employee_id, 'entry_date' => $lastDay, 'type' => 'payout',
                        'employee_amount' => -$pf['employee'], 'employer_amount' => -$pf['employer'], 'note' => __('Final settlement')]);
                }
            }
            $settlement->update(['status' => 'final', 'finalised_by' => $by->id, 'finalised_at' => now()]);
            $settlement->update(['pdf_path' => $this->pdf($settlement)]);

            return $settlement;
        });
    }

    public function pdf(FinalSettlement $settlement): string
    {
        $settlement->loadMissing(['employee.designation', 'employee.department']);
        $path = 'settlements/' . $settlement->employee->code . '-' . $settlement->last_working_day->format('Ymd') . '.pdf';
        $pdf = Pdf::loadView('pdf.final-settlement', ['settlement' => $settlement, 'company' => tenant('company_name'), 'app' => Brand::name()]);
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /** Per-day rate of a monthly salary in the last pay month, by the Payroll rules divisor (same rule as payroll). */
    private function perDay(SalaryStructure $structure, string $lastDay): int
    {
        [$from, $to] = $this->cycle->dates(...$this->cycle->periodFor($lastDay));
        $periodDays = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;

        return PayslipCalculator::perDayRate((int) $structure->basic + $structure->allowancesTotal(), $periodDays,
            $this->settings->get('payroll_day_divisor', 'calendar'));
    }

    /**
     * Salary for the last pay month up to the last working day, unless a payroll run already paid it. Never more
     * than a full month's salary.
     *
     * @return list<array<string, mixed>>
     */
    private function lastSalary(Employee $employee, SalaryStructure $structure, string $lastDay, int $monthlyPerDay): array
    {
        [$y, $m] = $this->cycle->periodFor($lastDay);
        $paid = Payslip::query()->where('employee_id', $employee->id)->whereHas('run.period', fn ($q) => $q->where(['year' => $y, 'month' => $m]))->exists();
        if ($paid) {
            return [];
        }
        [$from, $to] = $this->cycle->dates($y, $m);
        $attendance = AttendanceDay::query()->where('employee_id', $employee->id)->whereBetween('date', [$from, min($to, $lastDay)])->get();
        $summary = (new PeriodCalculator)->summarise($attendance->map(fn (AttendanceDay $d) => $d->toResult()), $this->rules->forEmployee($employee, $to));
        $isDailyWage = $employee->employment_type === EmploymentType::DailyWage;
        $perDay = $isDailyWage ? (int) $structure->per_day_rate : $monthlyPerDay;
        $days = $summary->payableDays;
        $amount = (int) round($perDay * $days);
        if (! $isDailyWage) {
            $amount = min($amount, (int) $structure->basic + $structure->allowancesTotal());
        }

        return $amount > 0 ? [['label' => __('Salary :from – :to (:d days × Rs :r)', ['from' => $from, 'to' => min($to, $lastDay),
            'd' => rtrim(rtrim(number_format($days, 2), '0'), '.'), 'r' => number_format($perDay)]), 'type' => 'earning', 'amount' => $amount, 'code' => 'salary']] : [];
    }

    /** @return list<array<string, mixed>> */
    private function leaveEncashment(Employee $employee, string $lastDay, int $perDay): array
    {
        $lines = [];
        foreach (LeaveType::query()->where('encashable', true)->get() as $type) {
            $days = $this->balances->balance($employee, $type, $this->leaveYear->yearFor($lastDay))->remaining();
            if ($days > 0) {
                $lines[] = ['label' => __('Leave encashment — :t (:d days × Rs :r)', ['t' => $type->name, 'd' => $days, 'r' => number_format($perDay)]),
                    'type' => 'earning', 'amount' => (int) round($days * $perDay), 'code' => 'leave_encashment'];
            }
        }

        return $lines;
    }

    /** @param list<array{type: string, amount: int}> $lines */
    private static function net(array $lines): int
    {
        return (int) array_sum(array_map(fn (array $l) => $l['type'] === 'deduction' ? -$l['amount'] : $l['amount'], $lines));
    }

    private function assert(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['settlement' => $message]);
        }
    }
}
