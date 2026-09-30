<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\AttendanceDay;

/** R7 — Overtime report (overtime already rounded by the engine). */
final class OvertimeReport extends DateRangeReport
{
    public function title(): string
    {
        return __('Overtime');
    }

    public function columns(array $filters): array
    {
        return ['date' => 'Date', 'code' => 'Code', 'name' => 'Name', 'status' => 'Day', 'worked' => 'Worked', 'expected' => 'Expected', 'ot' => 'OT', 'ot_min' => 'OT (min)'];
    }

    public function rows(array $filters): array
    {
        $employees = $this->employees($filters)->get()->keyBy('id');

        return AttendanceDay::query()->whereIn('employee_id', $employees->keys())
            ->whereBetween('date', [$filters['from'], $filters['to']])->where('overtime_min', '>', 0)
            ->orderBy('date')->get()
            ->map(fn (AttendanceDay $d) => [
                'date' => $d->date->format('Y-m-d'),
                'code' => $employees[$d->employee_id]->code,
                'name' => $employees[$d->employee_id]->fullName(),
                'status' => $d->status?->name,
                'worked' => self::hm($d->worked_min),
                'expected' => self::hm($d->expected_min),
                'ot' => self::hm($d->overtime_min),
                'ot_min' => $d->overtime_min,
            ])->all();
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Total OT hours' => round(array_sum(array_column($rows, 'ot_min')) / 60, 1)];
    }
}
