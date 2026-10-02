<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRulesFactory;
use Filament\Forms\Components\TextInput;

/** R18 — Attendance summary: one line per employee with day counts for a pay-cycle month (legacy TMS layout). */
final class AttendanceSummaryReport extends Report
{
    public function title(): string
    {
        return __('Attendance summary');
    }

    public function filterSchema(): array
    {
        return [TextInput::make('month')->type('month')->required(), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        return ['month' => $this->currentCycleMonth()];
    }

    public function subtitle(array $filters): string
    {
        return $this->cycleLabel($filters['month']);
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        return [
            'code' => 'Emp No', 'name' => 'Employee name', 'on_time' => 'Present (on time)', 'late' => 'Present (late)',
            'half' => 'Half day', 'leave' => 'On leave', 'absent' => 'Absent', 'exempted' => 'Exempted', 'off' => 'Off day',
            'holiday' => 'Holiday', 'total' => 'Total days', 'work' => 'Work days',
        ];
    }

    public function rows(array $filters): array
    {
        [$start, $end] = $this->cycleRange($filters['month']);
        $employees = $this->employees($filters)->get();
        $days = AttendanceDay::query()->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])->get()->groupBy('employee_id');
        $rules = app(PeriodRulesFactory::class);
        $calculator = new PeriodCalculator;

        $rows = [];
        foreach ($employees as $e) {
            $own = $days->get($e->id);
            if ($own === null) {
                continue;
            }
            $s = $calculator->summarise($own->map(fn (AttendanceDay $d) => $d->toResult()), $rules->forEmployee($e, $end->format('Y-m-d')));
            $latePresent = $own->filter(fn (AttendanceDay $d) => $d->status === AttendanceStatus::Present && in_array('late', $d->flags ?? [], true))->count();
            $rows[] = [
                'code' => $e->code,
                'name' => $e->fullName(),
                'on_time' => ($s->presentDays + $s->officialDutyDays + $s->wfhDays - $latePresent) ?: null,
                'late' => $latePresent ?: null,
                'half' => ($s->halfDays + $s->halfDayLeaves) ?: null,
                'leave' => ($s->paidLeaveDays + $s->unpaidLeaveDays) ?: null,
                'absent' => $s->absentDays ?: null,
                'exempted' => $s->exemptedDays ?: null,
                'off' => $s->weeklyOffs ?: null,
                'holiday' => $s->holidays ?: null,
                'total' => $s->calendarDays,
                'work' => $s->payableDays,
            ];
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return [
            'Employees' => count($rows),
            'Absent days' => array_sum(array_column($rows, 'absent')),
            'Late arrivals' => array_sum(array_column($rows, 'late')),
        ];
    }
}
