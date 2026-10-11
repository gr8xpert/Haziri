<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets\Analytics;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Services\Analytics\PunctualityScore;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRulesFactory;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Hero numbers: company punctuality score and last month's absenteeism cost (deduction days × per-day rate).
 * Deduction days use each employee's own rules, as Monthly totals and payroll do.
 */
class HeadlineStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $last30 = AttendanceDay::query()->where('date', '>=', CarbonImmutable::now($tz)->subDays(29)->format('Y-m-d'))->get();
        $score = PunctualityScore::of($last30);

        // Last completed pay-cycle month.
        $cycle = app(PayCycle::class);
        [$y, $m] = $cycle->periodFor(CarbonImmutable::now($tz)->format('Y-m-d'));
        $previous = CarbonImmutable::create($y, $m, 1)->subMonth();
        [$start, $end] = $cycle->range($previous->year, $previous->month);
        $periodDays = (int) $start->diffInDays($end) + 1;
        $days = AttendanceDay::query()->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])->get()->groupBy('employee_id');
        $employees = Employee::query()->whereIn('id', $days->keys())->get()->keyBy('id');
        $rules = app(PeriodRulesFactory::class);
        $calculator = new PeriodCalculator;
        $cost = 0;
        $deduction = 0.0;
        foreach ($days as $employeeId => $list) {
            $employee = $employees->get($employeeId);
            if ($employee === null) {
                continue;
            }
            $summary = $calculator->summarise($list->map(fn (AttendanceDay $d) => $d->toResult()), $rules->forEmployee($employee, $end->format('Y-m-d')));
            $deduction += $summary->deductionDays;
            $structure = SalaryStructure::effectiveFor($employeeId, $end->format('Y-m-d'));
            if ($structure) {
                $perDay = $structure->per_day_rate ?: ($structure->basic + $structure->allowancesTotal()) / $periodDays;
                $cost += (int) round($summary->deductionDays * $perDay);
            }
        }

        return [
            Stat::make(__('Punctuality score (30 days)'), $score['score'] . ' / 100')
                ->description(__(':l late, :a absent of :w working days', ['l' => $score['late'], 'a' => $score['absent'], 'w' => $score['working']])),
            Stat::make(__('Deduction days, :m', ['m' => $previous->format('F')]), rtrim(rtrim(number_format($deduction, 1), '0'), '.')),
            Stat::make(__('Absenteeism cost, :m', ['m' => $previous->format('F')]), 'Rs ' . number_format($cost))
                ->description(__('Needs salary structures; employees without one count as Rs 0')),
        ];
    }
}
