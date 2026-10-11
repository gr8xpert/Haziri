<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

use App\Enums\MultiPunchMode;

/**
 * A shift with any overrides (Ramadan, Jummah…) already merged in, for one specific date.
 * Times are "HH:MM" wall-clock in the employee's timezone.
 */
final readonly class ResolvedShift
{
    /**
     * @param  list<int>  $weeklyOffDays  0 = Sunday … 6 = Saturday
     */
    public function __construct(
        public ?int $shiftId,
        public string $startTime,
        public string $endTime,
        public bool $crossesMidnight = false,
        public int $graceInMin = 15,
        public int $graceOutMin = 5,
        public ?int $lateAfterMin = null,
        public ?int $halfDayIfLateAfterMin = 120,
        public ?int $halfDayIfWorkedLessThanMin = 240,
        public int $earlyOutBeforeMin = 30,
        public ?int $autoCloseMissingOutAfterMin = null,
        public int $breakMin = 0,
        public MultiPunchMode $multiPunchMode = MultiPunchMode::FirstLast,
        public ?int $overtimeAfterMin = null,
        public ?int $maxOvertimeMin = null,
        public array $weeklyOffDays = [0],
        public ?int $overrideId = null,
    ) {}

    /** Minutes after scheduled start before an arrival counts as late. */
    public function lateThresholdMin(): int
    {
        return $this->lateAfterMin ?? $this->graceInMin;
    }

    /** Minutes before scheduled end a departure must exceed to count as early out; 0 means only the out grace applies. */
    public function earlyOutThresholdMin(): int
    {
        return max($this->graceOutMin, $this->earlyOutBeforeMin);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
