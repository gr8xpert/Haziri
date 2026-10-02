<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/**
 * The tenant's leave year. With `leave_year_start_month` = 10 the leave year 2025 runs 1 Oct 2025 → 30 Sep 2026
 * (label "2025-26"). Leave balances are keyed by the calendar year in which their leave year starts.
 */
final class LeaveYear
{
    public function __construct(private readonly TenantSettings $settings) {}

    public function startMonth(): int
    {
        return max(1, min(12, (int) $this->settings->get('leave_year_start_month', 1)));
    }

    public function yearFor(string $date): int
    {
        $d = CarbonImmutable::parse(substr($date, 0, 10));

        return $d->month >= $this->startMonth() ? $d->year : $d->year - 1;
    }

    public function current(): int
    {
        return $this->yearFor(CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d'));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function range(int $year): array
    {
        $start = CarbonImmutable::create($year, $this->startMonth(), 1)->startOfDay();

        return [$start, $start->addYear()->subDay()];
    }

    /** 0-based month index of $date inside leave year $year: 0 = first month, 11 = last; <0 before, >11 after. */
    public function monthIndex(int $year, string $date): int
    {
        $d = CarbonImmutable::parse(substr($date, 0, 10));

        return ($d->year - $year) * 12 + $d->month - $this->startMonth();
    }

    public function label(int $year): string
    {
        return $this->startMonth() === 1 ? (string) $year : $year . '-' . substr((string) ($year + 1), -2);
    }

    /** @return array<int, string> year => label, around the current year */
    public function options(int $before = 2, int $after = 1): array
    {
        $current = $this->current();
        $years = range($current - $before, $current + $after);

        return array_combine($years, array_map(fn (int $y) => $this->label($y), $years));
    }
}
