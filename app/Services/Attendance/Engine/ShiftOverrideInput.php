<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

/**
 * A shift_overrides row already known to cover the date and the employee's scope.
 * $changes holds only the columns the override sets (non-null), keyed by ResolvedShift property name.
 */
final readonly class ShiftOverrideInput
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public int $id,
        public int $priority,
        public array $changes,
        /** Restrict to one weekday (5 = Friday for Jummah); null = every day in range. */
        public ?int $weekday = null,
    ) {}
}
