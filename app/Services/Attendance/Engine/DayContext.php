<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

use App\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;

/**
 * Everything the engine needs to resolve one employee-day. Built by DayContextLoader from the DB,
 * or directly in tests. The engine itself never touches the database or the clock.
 */
final readonly class DayContext
{
    /**
     * @param  string  $date  Y-m-d, local to $timezone
     * @param  list<PunchInput>  $punches  valid punches around the date (the engine filters to the shift window)
     */
    public function __construct(
        public string $date,
        public string $timezone,
        public CarbonImmutable $now,
        public ?string $joinedOn,
        public ?string $leftOn,
        public ?ResolvedShift $shift,
        public array $punches = [],
        public ?int $holidayId = null,
        public bool $isHoliday = false,
        public ?LeaveInput $leave = null,
        /** OfficialDuty or Wfh marker from HR / an approved correction. */
        public ?AttendanceStatus $marker = null,
        public ?AttendanceStatus $manualOverride = null,
        public bool $overtimeEligible = false,
        public EngineSettings $settings = new EngineSettings,
    ) {}
}
