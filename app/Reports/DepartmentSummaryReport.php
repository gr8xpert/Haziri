<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;
use App\Models\Department;

/** R11 — Department summary: headcount, present %, late %, absent %, OT hours. */
final class DepartmentSummaryReport extends DateRangeReport
{
    public function title(): string
    {
        return __('Department summary');
    }

    public function columns(array $filters): array
    {
        return ['department' => 'Department', 'headcount' => 'Headcount', 'working_days' => 'Working days',
            'present_pct' => 'Present %', 'late_pct' => 'Late %', 'absent_pct' => 'Absent %', 'ot_hours' => 'OT hours'];
    }

    public function rows(array $filters): array
    {
        $employees = $this->employees($filters)->get();
        $days = AttendanceDay::query()->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$filters['from'], $filters['to']])->get()->groupBy('employee_id');
        $names = Department::pluck('name', 'id');

        $rows = [];
        foreach ($employees->groupBy(fn ($e) => $e->department_id ?? 0) as $deptId => $members) {
            $all = $members->flatMap(fn ($e) => $days->get($e->id, collect()));
            $working = $all->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::HalfDay, AttendanceStatus::Absent,
                AttendanceStatus::OfficialDuty, AttendanceStatus::Wfh]);
            $n = max(1, $working->count());
            $present = $working->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::HalfDay, AttendanceStatus::OfficialDuty, AttendanceStatus::Wfh])->count();
            $rows[] = [
                'department' => $names[$deptId] ?? __('(none)'),
                'headcount' => $members->count(),
                'working_days' => $working->count(),
                'present_pct' => round(100 * $present / $n, 1),
                'late_pct' => round(100 * $working->filter(fn ($d) => in_array('late', $d->flags ?? [], true))->count() / $n, 1),
                'absent_pct' => round(100 * $working->where('status', AttendanceStatus::Absent)->count() / $n, 1),
                'ot_hours' => round($all->sum('overtime_min') / 60, 1),
            ];
        }

        return $rows;
    }
}
