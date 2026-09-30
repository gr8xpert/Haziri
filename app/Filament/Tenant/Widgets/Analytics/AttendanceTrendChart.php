<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Widgets\Analytics;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/** 12-month attendance rate (present-type days ÷ working days). Single series: no legend, one validated hue. */
class AttendanceTrendChart extends ChartWidget
{
    protected ?string $heading = 'Attendance rate, last 12 months (%)';

    protected static bool $isDiscovered = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $start = CarbonImmutable::now($tz)->startOfMonth()->subMonths(11);
        $present = [AttendanceStatus::Present->value, AttendanceStatus::HalfDay->value, AttendanceStatus::OfficialDuty->value, AttendanceStatus::Wfh->value];
        $working = [...$present, AttendanceStatus::Absent->value];

        $rows = AttendanceDay::query()
            ->where('date', '>=', $start->format('Y-m-d'))
            ->whereIn('status', $working)
            ->select(DB::raw("DATE_FORMAT(date, '%Y-%m') as ym"), 'status', DB::raw('count(*) as n'))
            ->groupBy('ym', 'status')->get()->groupBy('ym');

        $labels = [];
        $values = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->addMonths($i);
            $month = $rows->get($m->format('Y-m'), collect());
            $total = (int) $month->sum('n');
            $labels[] = $m->format('M y');
            $values[] = $total ? round(100 * $month->whereIn('status', $present)->sum('n') / $total, 1) : null;
        }

        return [
            'datasets' => [[
                'label' => 'Attendance rate',
                'data' => $values,
                'borderColor' => '#2a78d6',
                'backgroundColor' => '#2a78d6',
                'borderWidth' => 2,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'spanGaps' => false,
                'tension' => 0,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false], 'tooltip' => ['mode' => 'index', 'intersect' => false]],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => ['y' => ['min' => 0, 'max' => 100, 'grid' => ['color' => 'rgba(127,127,127,0.15)']], 'x' => ['grid' => ['display' => false]]],
        ];
    }
}
