<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;

/**
 * Output of the engine for one employee-day. A null status means "not decidable yet"
 * (e.g. today with no punches — absence is only final once the day is over).
 */
final readonly class DayResult
{
    /**
     * @param  list<AttendanceFlag>  $flags
     * @param  array<string, int>  $sourceSummary  punch count per source
     */
    public function __construct(
        public ?AttendanceStatus $status,
        public array $flags = [],
        public ?CarbonImmutable $firstIn = null,
        public ?CarbonImmutable $lastOut = null,
        public int $workedMin = 0,
        public int $breakMin = 0,
        public int $lateMin = 0,
        public int $earlyOutMin = 0,
        public int $overtimeMin = 0,
        public int $expectedMin = 0,
        public ?int $shiftId = null,
        public ?int $overrideId = null,
        public ?int $holidayId = null,
        public ?int $leaveRequestId = null,
        public array $sourceSummary = [],
    ) {}

    public function hasFlag(AttendanceFlag $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }

    /** @return list<string> */
    public function flagValues(): array
    {
        return array_map(fn (AttendanceFlag $f) => $f->value, $this->flags);
    }
}
