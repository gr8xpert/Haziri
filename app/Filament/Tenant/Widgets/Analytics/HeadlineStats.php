<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets\Analytics;

use App\Models\AttendanceDay;
use App\Models\SalaryStructure;
use App\Services\Analytics\PunctualityScore;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRules;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Hero numbers: company punctuality score and last month's absenteeism cost (deduction days × per-day rate). */
class HeadlineStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $last30 = AttendanceDay::query()->where('date', '>=', CarbonImmutable::now($tz)->subDays(29)->format('Y-m-d'))->get();
        $score = PunctualityScore::of($last30);

        $month = CarbonImmutable::now($tz)->subMonthNoOverflow()->startOfMonth();
        $days = AttendanceDay::query()->whereBetween('date', [$month->format('Y-m-d'), $month->endOfMonth()->format('Y-m-d')])->get()->groupBy('employee_id');
        $settings = app(TenantSettings::class)->all();
        $cost = 0;
        $deduction = 0.0;
        foreach ($days as $employeeId => $list) {
            $summary = (new PeriodCalculator)->summarise($list->map(fn ($d) => $d->toResult()), PeriodRules::fromSettings($settings, false, false));
            $deduction += $summary->deductionDays;
            $structure = SalaryStructure::effectiveFor($employeeId, $month->endOfMonth()->format('Y-m-d'));
            if ($structure) {
                $perDay = $structure->per_day_rate ?: ($structure->basic + $structure->allowancesTotal()) / $month->daysInMonth;
                $cost += (int) round($summary->deductionDays * $perDay);
            }
        }

        return [
            Stat::make(__('Punctuality score (30 days)'), $score['score'] . ' / 100')
                ->description(__(':l late, :a absent of :w working days', ['l' => $score['late'], 'a' => $score['absent'], 'w' => $score['working']])),
            Stat::make(__('Deduction days, :m', ['m' => $month->format('F')]), rtrim(rtrim(number_format($deduction, 1), '0'), '.')),
            Stat::make(__('Absenteeism cost, :m', ['m' => $month->format('F')]), 'Rs ' . number_format($cost))
                ->description(__('Needs salary structures; employees without one count as Rs 0')),
        ];
    }
}
