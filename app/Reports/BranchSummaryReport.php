<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRulesFactory;
use App\Support\BranchAccess;
use Filament\Forms\Components\TextInput;

/** R23 — Branch summary: one line per branch for a pay-cycle month, to compare shops side by side. */
final class BranchSummaryReport extends Report
{
    public function title(): string
    {
        return __('Branch summary');
    }

    public function filterSchema(): array
    {
        return [TextInput::make('month')->type('month')->required()];
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
        return ['branch' => 'Branch', 'staff' => 'Staff', 'present' => 'Present days', 'late' => 'Late arrivals', 'half' => 'Half days',
            'absent' => 'Absent days', 'leave' => 'Leave days', 'ot' => 'Overtime (h)', 'deduction' => 'Deduction days', 'rate' => 'Attendance %'];
    }

    public function rows(array $filters): array
    {
        [$start, $end] = $this->cycleRange($filters['month']);
        $employees = $this->employees([])->get()->groupBy('branch_id');
        $days = AttendanceDay::query()->whereIn('employee_id', $employees->flatten()->pluck('id'))
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])->get()->groupBy('employee_id');
        $ids = BranchAccess::ids();
        $branches = Branch::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('name')->pluck('name', 'id');
        $rules = app(PeriodRulesFactory::class);
        $calculator = new PeriodCalculator;

        $rows = [];
        foreach ($branches->all() + [0 => __('No branch')] as $branchId => $name) { // + keeps the branch ids as keys
            $people = $employees->get($branchId === 0 ? '' : $branchId, collect());
            $t = ['present' => 0, 'late' => 0, 'half' => 0, 'absent' => 0, 'leave' => 0, 'ot' => 0, 'deduction' => 0.0, 'staff' => 0];
            foreach ($people as $e) {
                $own = $days->get($e->id);
                if ($own === null) {
                    continue;
                }
                $s = $calculator->summarise($own->map(fn (AttendanceDay $d) => $d->toResult()), $rules->forEmployee($e, $end->format('Y-m-d')));
                $t['staff']++;
                $t['present'] += $s->presentDays + $s->officialDutyDays + $s->wfhDays;
                $t['late'] += $s->lateCount;
                $t['half'] += $s->halfDays + $s->halfDayLeaves;
                $t['absent'] += $s->absentDays;
                $t['leave'] += $s->paidLeaveDays + $s->unpaidLeaveDays;
                $t['ot'] += $s->overtimeMinutes;
                $t['deduction'] += $s->deductionDays;
            }
            if ($t['staff'] === 0) {
                continue;
            }
            $worked = $t['present'] + $t['half'] * 0.5;
            $rows[] = [
                'branch' => $name,
                'staff' => $t['staff'],
                'present' => $t['present'],
                'late' => $t['late'],
                'half' => $t['half'],
                'absent' => $t['absent'],
                'leave' => $t['leave'],
                'ot' => round($t['ot'] / 60, 1),
                'deduction' => round($t['deduction'], 1),
                'rate' => ($worked + $t['absent']) > 0 ? round($worked / ($worked + $t['absent']) * 100, 1) : null,
            ];
        }

        return $rows;
    }
}
