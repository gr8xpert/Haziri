<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets\Analytics;

use App\Models\AttendanceDay;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Top 10 late arrivals, last 30 days — horizontal bars, one series, rounded data ends. */
class TopLateChart extends ChartWidget
{
    protected ?string $heading = 'Most late arrivals, last 30 days';

    protected static bool $isDiscovered = false;

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $from = CarbonImmutable::now(tenant('timezone'))->subDays(29)->format('Y-m-d');
        $counts = AttendanceDay::query()->where('date', '>=', $from)->whereJsonContains('flags', 'late')
            ->selectRaw('employee_id, count(*) as n')->groupBy('employee_id')->orderByDesc('n')->limit(10)->pluck('n', 'employee_id');
        $names = Employee::withTrashed()->whereIn('id', $counts->keys())->get()->mapWithKeys(fn (Employee $e) => [$e->id => $e->fullName()]);

        return [
            'datasets' => [[
                'label' => 'Late days',
                'data' => $counts->values()->all(),
                'backgroundColor' => '#2a78d6',
                'borderRadius' => 4,
                'borderSkipped' => 'start',
                'maxBarThickness' => 18,
            ]],
            'labels' => $counts->keys()->map(fn ($id) => $names[$id] ?? '#' . $id)->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => 'rgba(127,127,127,0.15)']], 'y' => ['grid' => ['display' => false]]],
        ];
    }
}
