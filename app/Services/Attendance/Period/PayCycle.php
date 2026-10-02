<?php

declare(strict_types=1);

namespace App\Services\Attendance\Period;

use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/**
 * The tenant's attendance/payroll month. With `pay_cycle_start_day` = 1 a period is the calendar month;
 * with e.g. 21 the period "June 2026" runs 21 May → 20 June (named after the month it ends in).
 */
final class PayCycle
{
    public function __construct(private readonly TenantSettings $settings) {}

    public function startDay(): int
    {
        return max(1, min(28, (int) $this->settings->get('pay_cycle_start_day', 1)));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} first and last day of the period */
    public function range(int $year, int $month): array
    {
        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $day = $this->startDay();
        if ($day === 1) {
            return [$monthStart, $monthStart->endOfMonth()->startOfDay()];
        }

        return [$monthStart->subMonthNoOverflow()->day($day), $monthStart->day($day - 1)];
    }

    /** @return array{0: string, 1: string} Y-m-d bounds */
    public function dates(int $year, int $month): array
    {
        [$start, $end] = $this->range($year, $month);

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    /** @return array{0: int, 1: int} year and month of the period that contains $date */
    public function periodFor(string $date): array
    {
        $d = CarbonImmutable::parse(substr($date, 0, 10));
        $day = $this->startDay();
        if ($day > 1 && $d->day >= $day) {
            $d = $d->startOfMonth()->addMonthNoOverflow();
        }

        return [$d->year, $d->month];
    }

    public function label(int $year, int $month): string
    {
        $name = CarbonImmutable::create($year, $month, 1)->format('F Y');
        if ($this->startDay() === 1) {
            return $name;
        }
        [$start, $end] = $this->range($year, $month);

        return "{$name} ({$start->format('d M')} – {$end->format('d M')})";
    }
}
