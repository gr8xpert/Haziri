<?php

declare(strict_types=1);

namespace App\Actions\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\PeriodStatus;
use App\Models\Advance;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PfLedgerEntry;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\Recipient;
use App\Services\Payroll\IncomeTax;
use App\Services\Payroll\PayslipCalculator;
use App\Services\Payroll\StatutoryPayroll;
use App\Services\Settings\TenantSettings;
use App\Services\Webhooks\Webhooks;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Payroll run lifecycle (spec §9 phase 3): generate (draft, re-runnable) → finalise (advances recovered, PDFs, notify). */
final class PayrollActions
{
    public function __construct(
        private readonly PayslipCalculator $calculator,
        private readonly StatutoryPayroll $statutory,
        private readonly TenantSettings $settings,
        private readonly Notifier $notifier,
        private readonly IncomeTax $tax,
    ) {}

    public function generate(AttendancePeriod $period, User $by): PayrollRun
    {
        $this->assert($by->can('payroll.manage'), __('You cannot run payroll.'));
        $this->assert($this->settings->featureEnabled('payroll_lite'), __('Payroll is not enabled for your company.'));
        $this->assert($period->status === PeriodStatus::Locked, __('Lock the month before running payroll.'));
        $existing = PayrollRun::query()->where('period_id', $period->id)->first();
        $this->assert($existing?->status !== 'final', __('Payroll for this month is already final.'));

        [$start, $end] = array_map(fn (string $d) => CarbonImmutable::parse($d), $period->dates());
        $summaries = $period->summary['employees'] ?? [];
        $divisor = $this->settings->get('payroll_day_divisor', 'calendar');

        return DB::transaction(function () use ($period, $by, $existing, $start, $end, $summaries, $divisor) {
            $run = $existing ?? PayrollRun::create(['period_id' => $period->id, 'status' => 'draft', 'generated_by' => $by->id]);
            $run->payslips()->delete();

            $overtime = AttendanceDay::query()->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->where('overtime_min', '>', 0)->get()->groupBy('employee_id');

            $totals = ['employees' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0, 'skipped_no_structure' => 0];
            foreach ($summaries as $employeeId => $summary) {
                $employee = Employee::withTrashed()->find($employeeId);
                $structure = $employee ? SalaryStructure::effectiveFor($employee->id, $end->format('Y-m-d')) : null;
                if ($structure === null) {
                    $totals['skipped_no_structure']++;

                    continue;
                }
                $ot = $overtime->get($employee->id, collect());
                $holidayOt = (int) $ot->whereIn('status', [AttendanceStatus::Holiday, AttendanceStatus::WeeklyOff])->sum('overtime_min');
                $advances = Advance::query()->where('employee_id', $employee->id)->where('status', 'active')
                    ->where('issued_on', '<=', $end->format('Y-m-d'))->where('remaining', '>', 0)->get()->all();

                $isDailyWage = $employee->employment_type === EmploymentType::DailyWage;
                $slip = $this->calculator->calculate($structure, $summary, $isDailyWage,
                    (int) $start->diffInDays($end) + 1, $divisor, (int) $ot->sum('overtime_min') - $holidayOt, $holidayOt, $advances);
                $slip = $this->statutory->apply($employee, $structure, $slip, $period->year, $period->month, $end->format('Y-m-d'), $isDailyWage);

                Payslip::create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'lines' => $slip['lines'],
                    'gross' => $slip['gross'], 'deductions' => $slip['deductions'], 'net' => $slip['net'], 'meta' => $slip['meta']]);
                $totals['employees']++;
                $totals['gross'] += $slip['gross'];
                $totals['deductions'] += $slip['deductions'];
                $totals['net'] += $slip['net'];
            }
            // This tax year's rates not entered yet: last year's were used, so tell HR.
            $taxYear = $this->tax->taxYearFor($end->format('Y-m-d'));
            if ($this->settings->featureEnabled('payroll_tax') && ! $this->tax->hasSlabsFor($taxYear)) {
                $totals['tax_rates_missing'] = $taxYear;
            }
            $run->update(['totals' => $totals, 'generated_by' => $by->id, 'status' => 'draft']);

            return $run;
        });
    }

    public function finalise(PayrollRun $run, User $by): PayrollRun
    {
        $this->assert($by->can('payroll.manage'), __('You cannot run payroll.'));
        $this->assert($run->status === 'draft', __('This payroll run is already final.'));

        DB::transaction(function () use ($run) {
            $end = $run->period->dates()[1];
            foreach ($run->payslips()->with('employee')->get() as $slip) {
                foreach (Advance::query()->where('employee_id', $slip->employee_id)->where('status', 'active')
                    ->where('issued_on', '<=', $end)->where('remaining', '>', 0)->get() as $advance) {
                    $remaining = $advance->remaining - $advance->instalment();
                    $advance->update(['remaining' => $remaining, 'status' => $remaining <= 0 ? 'settled' : 'active']);
                }
                $slip->update(['pdf_path' => $this->pdf($slip)]);
                if (($slip->meta['pf_ee'] ?? 0) > 0 || ($slip->meta['pf_er'] ?? 0) > 0) {
                    PfLedgerEntry::create(['employee_id' => $slip->employee_id, 'payroll_run_id' => $run->id, 'entry_date' => $end,
                        'type' => 'contribution', 'employee_amount' => (int) ($slip->meta['pf_ee'] ?? 0),
                        'employer_amount' => (int) ($slip->meta['pf_er'] ?? 0), 'note' => $run->label()]);
                }
            }
            $run->update(['status' => 'final']);
        });
        Webhooks::dispatch('payroll.generated', ['month' => $run->label(), 'totals' => $run->fresh()->totals]);

        foreach ($run->payslips()->with('employee')->get() as $slip) {
            $this->notifier->send('payslip_ready', [Recipient::fromEmployee($slip->employee)],
                ['name' => $slip->employee->first_name, 'month' => $run->label(), 'net' => number_format($slip->net)]);
            $slip->update(['sent_at' => now()]);
        }

        return $run;
    }

    public function pdf(Payslip $slip): string
    {
        $slip->loadMissing(['employee.designation', 'employee.department', 'run.period']);
        $path = 'payslips/' . $slip->run->period->year . '-' . str_pad((string) $slip->run->period->month, 2, '0', STR_PAD_LEFT)
            . '/' . $slip->employee->code . '.pdf';
        $pdf = Pdf::loadView('pdf.payslip', ['slip' => $slip, 'company' => tenant('company_name'), 'month' => $slip->run->label()]);
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Bank transfer file with configurable columns (setting payroll_bank_csv_columns,
     * default account,name,amount,reference). One row per payslip with a positive net; bank details come from the
     * salary structure in force on the run month's last day, like the pay itself.
     */
    public function bankCsv(PayrollRun $run): string
    {
        $columns = (array) $this->settings->get('payroll_bank_csv_columns', ['account', 'name', 'amount', 'reference']);
        $monthEnd = $run->period->dates()[1];
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $columns);
        foreach ($run->payslips()->with('employee')->get() as $slip) {
            if ($slip->net <= 0) {
                continue;
            }
            $structure = SalaryStructure::effectiveFor($slip->employee_id, $monthEnd);
            $values = [
                'account' => $structure?->bank_account, 'bank' => $structure?->bank_name, 'name' => $slip->employee->fullName(),
                'code' => $slip->employee->code, 'cnic' => $slip->employee->cnic, 'amount' => $slip->net,
                'reference' => 'Salary ' . $run->label(),
            ];
            fputcsv($out, array_map(fn ($c) => self::csvSafe($values[$c] ?? ''), $columns));
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * Text that Excel would run as a formula (=, +, -, @, tab, CR at the start) gets a leading apostrophe, so a name
     * like "=HYPERLINK(…)" stays text when HR opens the file. Numbers and ordinary text are written unchanged.
     */
    private static function csvSafe(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && ! is_numeric($value) && str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value;
    }

    /** Letter to the company's bank asking it to transfer each net salary (PDF bytes). */
    public function bankLetter(PayrollRun $run, ?string $bankName = null): string
    {
        $monthEnd = $run->period->dates()[1];
        $rows = [];
        foreach ($run->payslips()->with('employee')->get()->sortBy('employee.code') as $slip) {
            if ($slip->net <= 0) {
                continue;
            }
            $structure = SalaryStructure::effectiveFor($slip->employee_id, $monthEnd);
            $rows[] = ['name' => $slip->employee->fullName(), 'code' => $slip->employee->code, 'bank' => $structure?->bank_name,
                'account' => $structure?->bank_account, 'amount' => $slip->net];
        }

        return Pdf::loadView('pdf.bank-letter', ['run' => $run, 'rows' => $rows, 'bank' => $bankName, 'company' => tenant('company_name'),
            'total' => array_sum(array_column($rows, 'amount')), 'date' => now(tenant('timezone'))->format('d F Y')])->output();
    }

    private function assert(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['payroll' => $message]);
        }
    }
}
