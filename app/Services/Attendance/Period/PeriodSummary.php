<?php

declare(strict_types=1);

namespace App\Services\Attendance\Period;

/** One employee's month in numbers; snapshotted into attendance_periods.summary on lock. */
final readonly class PeriodSummary
{
    public function __construct(
        public int $calendarDays,
        public int $presentDays,
        public int $halfDays,
        public int $halfDayLeaves,
        public int $absentDays,
        public int $paidLeaveDays,
        public int $unpaidLeaveDays,
        public int $holidays,
        public int $weeklyOffs,
        public int $officialDutyDays,
        public int $wfhDays,
        public int $pendingDays,
        public int $lateCount,
        public int $earlyOutCount,
        public int $workedMinutes,
        public int $overtimeMinutes,
        public int $lateRollupDays,
        public float $deductionDays,
        public float $payableDays,
    ) {}

    /** @return array<string, int|float> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
