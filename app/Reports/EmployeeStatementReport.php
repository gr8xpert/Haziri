<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\EmploymentType;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRules;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/** R10 — Employee monthly statement (printable PDF). */
final class EmployeeStatementReport extends Report
{
    public function title(): string
    {
        return __('Employee monthly statement');
    }

    public function filterSchema(): array
    {
        return [
            Select::make('employee_id')->label(__('Employee'))->required()->searchable()
                ->options(fn () => $this->employees([])->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])),
            TextInput::make('month')->type('month')->required(),
        ];
    }

    public function defaults(): array
    {
        return ['month' => CarbonImmutable::now($this->tz())->format('Y-m')];
    }

    public function ready(array $filters): bool
    {
        return ! empty($filters['employee_id']) && ! empty($filters['month']);
    }

    public function subtitle(array $filters): string
    {
        $e = isset($filters['employee_id']) ? Employee::find($filters['employee_id']) : null;

        return trim(($e ? "{$e->code} — {$e->fullName()} · " : '') . CarbonImmutable::parse(($filters['month'] ?? now()->format('Y-m')) . '-01')->format('F Y'));
    }

    public function columns(array $filters): array
    {
        return ['date' => 'Date', 'day' => 'Day', 'code' => 'Code', 'in' => 'In', 'out' => 'Out', 'worked' => 'Worked',
            'late' => 'Late', 'early' => 'Early', 'ot' => 'OT', 'flags' => 'Notes'];
    }

    public function rows(array $filters): array
    {
        if (empty($filters['employee_id'])) {
            return [];
        }
        $employee = $this->employees([])->find($filters['employee_id']);
        if ($employee === null) {
            return [];
        }
        $start = CarbonImmutable::parse($filters['month'] . '-01');
        $days = AttendanceDay::query()->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $start->endOfMonth()->format('Y-m-d')])->get()
            ->keyBy(fn (AttendanceDay $d) => $d->date->format('Y-m-d'));
        $leaveCodes = LeaveRequest::with('leaveType')->whereIn('id', $days->pluck('leave_request_id')->filter())->get()
            ->mapWithKeys(fn ($l) => [$l->id => $l->leaveType?->code ?? 'LV'])->all();
        $tz = $this->tz();

        $rows = [];
        foreach (CarbonPeriod::create($start, $start->endOfMonth()->startOfDay()) as $date) {
            $d = $days->get($date->format('Y-m-d'));
            $rows[] = [
                'date' => $date->format('d M'),
                'day' => $date->format('D'),
                'code' => StatusCodes::for($d, $leaveCodes),
                'in' => $d?->first_in?->setTimezone($tz)->format('H:i'),
                'out' => $d?->last_out?->setTimezone($tz)->format('H:i'),
                'worked' => self::hm($d->worked_min ?? 0),
                'late' => ($d->late_min ?? 0) ?: null,
                'early' => ($d->early_out_min ?? 0) ?: null,
                'ot' => self::hm($d->overtime_min ?? 0),
                'flags' => implode(', ', array_diff($d->flags ?? [], ['late', 'early_out'])),
            ];
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        if (empty($filters['employee_id'])) {
            return [];
        }
        $employee = Employee::find($filters['employee_id']);
        $start = CarbonImmutable::parse($filters['month'] . '-01');
        $days = AttendanceDay::query()->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->format('Y-m-d'), $start->endOfMonth()->format('Y-m-d')])->get();
        $s = (new PeriodCalculator)->summarise(
            $days->map(fn (AttendanceDay $d) => $d->toResult()),
            PeriodRules::fromSettings(app(TenantSettings::class)->all(), $employee->late_deduction_exempt, $employee->employment_type === EmploymentType::DailyWage),
        );

        return [
            'Present' => $s->presentDays, 'Half days' => $s->halfDays, 'Absent' => $s->absentDays, 'Paid leave' => $s->paidLeaveDays,
            'Unpaid leave' => $s->unpaidLeaveDays, 'Holidays' => $s->holidays, 'Weekly offs' => $s->weeklyOffs,
            'OD / WFH' => $s->officialDutyDays + $s->wfhDays, 'Lates' => $s->lateCount, 'Worked hours' => round($s->workedMinutes / 60, 1),
            'Overtime hours' => round($s->overtimeMinutes / 60, 1), 'Deduction days' => $s->deductionDays, 'Payable days' => $s->payableDays,
        ];
    }
}
