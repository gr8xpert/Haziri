<?php

declare(strict_types=1);

namespace App\Reports;

use App\Actions\Periods\PeriodActions;
use App\Enums\PeriodStatus;
use App\Filament\Tenant\Pages\Reports\MonthlyAttendance;
use App\Models\AttendancePeriod;
use Filament\Forms\Components\TextInput;

/**
 * One line per employee for a pay-cycle month: what each person was paid on. A locked month reads the snapshot saved
 * when it was locked; an open / in-review month is worked out live, so HR can check it before locking.
 * Each name links to the employee's day-by-day monthly attendance report.
 */
final class MonthSummaryReport extends Report
{
    public function title(): string
    {
        return __('Month summary');
    }

    public function filterSchema(): array
    {
        return [TextInput::make('month')->type('month')->required(), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        return ['month' => $this->currentCycleMonth()];
    }

    public function ready(array $filters): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}$/', (string) ($filters['month'] ?? ''));
    }

    public function subtitle(array $filters): string
    {
        $period = $this->period($filters['month']);
        $state = match (true) {
            $period?->status === PeriodStatus::Locked => __('Locked on :date — the numbers salaries were paid on', [
                'date' => $period->locked_at?->setTimezone($this->tz())->format('d M Y'),
            ]),
            $period?->status === PeriodStatus::Review => __('In review — live numbers, may still change'),
            default => __('Open — live numbers, may still change'),
        };

        return $this->cycleLabel($filters['month']) . ' · ' . $state;
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return [
            'code' => 'Emp No', 'name' => 'Employee name', 'present' => 'Present', 'late' => 'Late', 'half' => 'Half day',
            'leave' => 'Leave', 'od_wfh' => 'OD / WFH', 'absent' => 'Absent', 'overtime' => 'Overtime (h)',
            'deduction' => 'Deduction days', 'payable' => 'Payable days',
        ];
    }

    public function rows(array $filters): array
    {
        $month = (string) $filters['month'];
        $period = $this->period($month);
        $employees = $this->employees($filters)->get()->keyBy('id');
        $summaries = $period?->status === PeriodStatus::Locked && isset($period->summary['employees'])
            ? $period->summary['employees']
            : app(PeriodActions::class)->snapshot($period ?? $this->unsavedPeriod($month))['employees'];

        $rows = [];
        foreach ($summaries as $employeeId => $s) {
            $employee = $employees->get((int) $employeeId);
            if ($employee === null) {
                continue; // filtered out, or outside the user's branches / team
            }
            $rows[] = [
                'code' => $s['code'] ?? $employee->code,
                'name' => $s['name'] ?? $employee->fullName(),
                'present' => $s['presentDays'] ?: null,
                'late' => $s['lateCount'] ?: null,
                'half' => ($s['halfDays'] + $s['halfDayLeaves']) ?: null,
                'leave' => ($s['paidLeaveDays'] + $s['unpaidLeaveDays']) ?: null,
                'od_wfh' => ($s['officialDutyDays'] + $s['wfhDays']) ?: null,
                'absent' => $s['absentDays'] ?: null,
                'overtime' => $s['overtimeMinutes'] ? round($s['overtimeMinutes'] / 60, 1) : null,
                'deduction' => (float) $s['deductionDays'] ?: null,
                'payable' => (float) $s['payableDays'],
                '_url' => MonthlyAttendance::getUrl(['month' => $month, 'employee' => $employee->id]),
            ];
        }
        usort($rows, fn (array $a, array $b) => strnatcasecmp((string) $a['code'], (string) $b['code']));

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return [
            'Employees' => count($rows),
            'Absent days' => array_sum(array_column($rows, 'absent')),
            'Lates' => array_sum(array_column($rows, 'late')),
            'Deduction days' => array_sum(array_column($rows, 'deduction')),
        ];
    }

    private function period(string $month): ?AttendancePeriod
    {
        [$y, $m] = array_map('intval', explode('-', $month));

        return AttendancePeriod::query()->where(['year' => $y, 'month' => $m])->first();
    }

    /** A month nobody opened on the Months page yet: work it out without saving a row. */
    private function unsavedPeriod(string $month): AttendancePeriod
    {
        [$y, $m] = array_map('intval', explode('-', $month));

        return new AttendancePeriod(['year' => $y, 'month' => $m, 'status' => PeriodStatus::Open]);
    }
}
