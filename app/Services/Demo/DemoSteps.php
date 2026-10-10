<?php

declare(strict_types=1);

namespace App\Services\Demo;

use Carbon\CarbonImmutable;
use Closure;
use Throwable;

/**
 * Shared by the demo seeders: report progress, and run one optional step so a rule that refuses it (e.g. a date that
 * falls on a holiday this year) skips that bit instead of stopping the whole demo.
 */
trait DemoSteps
{
    /** @var Closure(string): void */
    private Closure $say;

    /**
     * @template T
     *
     * @param  Closure(): T  $step
     * @return T|null
     */
    private function attempt(string $what, Closure $step): mixed
    {
        try {
            return $step();
        } catch (Throwable $e) {
            ($this->say)("  skipped {$what}: {$e->getMessage()}");

            return null;
        }
    }

    /** Machine serials are unique across all companies: prefix the demo ones with this company's slug. */
    private static function serial(string $what): string
    {
        return strtoupper((string) tenant('slug')) . '-' . $what;
    }

    /** $day, or the nearest working day (Mon–Sat) in the given direction. */
    private static function workday(CarbonImmutable $day, int $direction = 1): CarbonImmutable
    {
        return $day->dayOfWeek === CarbonImmutable::SUNDAY ? $day->addDays($direction) : $day;
    }
}
