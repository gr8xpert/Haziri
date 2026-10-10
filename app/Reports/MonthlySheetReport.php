<?php

declare(strict_types=1);

namespace App\Reports;

use App\Filament\Tenant\Pages\Reports\MonthlyAttendance;
use App\Models\AttendanceDay;
use App\Models\LeaveRequest;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRulesFactory;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\TextInput;

/** R2 — Monthly attendance sheet: employees × days with status codes, totals and deduction days. */
final class MonthlySheetReport extends Report
{
    public function title(): string
    {
        return __('Monthly grid – all staff');
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
        $cols = ['code' => 'Code', 'name' => 'Name'];
        foreach ($this->period($filters) as $d) {
            $cols['d' . $d->format('j')] = $d->format('j');
        }

        return $cols + ['P' => 'P', 'A' => 'A', 'HD' => 'HD', 'LV' => 'LV', 'UL' => 'UL', 'EX' => 'EX', 'L' => 'Late',
            'adj' => 'Leave adj.', 'ded' => 'Ded.', 'pay' => 'Payable'];
    }

    public function rows(array $filters): array
    {
        $period = $this->period($filters);
        $from = $period->getStartDate()->format('Y-m-d');
        $to = $period->getEndDate()->format('Y-m-d');
        $employees = $this->employees($filters)->get();

        $days = AttendanceDay::query()->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$from, $to])->get()->groupBy('employee_id');
        $leaveCodes = LeaveRequest::query()->with('leaveType')->whereIn('id', $days->flatten()->pluck('leave_request_id')->filter()->unique())
            ->get()->mapWithKeys(fn (LeaveRequest $l) => [$l->id => $l->leaveType?->code ?? 'LV'])->all();
        $rules = app(PeriodRulesFactory::class);
        $calculator = new PeriodCalculator;

        $rows = [];
        foreach ($employees as $e) {
            $own = $days->get($e->id, collect())->keyBy(fn (AttendanceDay $d) => $d->date->format('Y-m-d'));
            // The name opens this employee's detailed monthly report for the same month.
            $row = ['code' => $e->code, 'name' => self::nameWithPayBasis($e), '_url' => MonthlyAttendance::getUrl(['month' => $filters['month'], 'employee' => $e->id], panel: 'tenant')];
            foreach ($period as $d) {
                $row['d' . $d->format('j')] = StatusCodes::for($own->get($d->format('Y-m-d')), $leaveCodes);
            }
            $summary = $calculator->summarise(
                $own->map(fn (AttendanceDay $d) => $d->toResult())->values(),
                $rules->forEmployee($e, $to),
            );
            $rows[] = $row + [
                'P' => $summary->presentDays + $summary->officialDutyDays + $summary->wfhDays,
                'A' => $summary->absentDays,
                'HD' => $summary->halfDays,
                'LV' => $summary->paidLeaveDays,
                'UL' => $summary->unpaidLeaveDays,
                'EX' => $summary->exemptedDays,
                'L' => $summary->lateCount,
                'adj' => $summary->leaveAdjustedDays ?: null,
                'ded' => $summary->deductionDays,
                'pay' => $summary->payableDays,
            ];
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return StatusCodes::legend() + self::dailyWageNote($rows);
    }

    private function period(array $filters): CarbonPeriod
    {
        [$start, $end] = $this->cycleRange($filters['month'] ?? $this->currentCycleMonth());

        return CarbonPeriod::create($start, $end);
    }
}
