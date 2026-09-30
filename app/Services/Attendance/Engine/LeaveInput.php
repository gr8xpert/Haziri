<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

use App\Enums\LeaveDayPart;

/** Approved leave covering the day being resolved. */
final readonly class LeaveInput
{
    public function __construct(
        public ?int $leaveRequestId,
        public bool $isPaid,
        public LeaveDayPart $dayPart = LeaveDayPart::Full,
        /** Day was pulled into leave by the sandwich rule (computed at approval time). */
        public bool $sandwich = false,
        /** Short leave (hours) — does not change status, only adds a flag. */
        public bool $isShortLeave = false,
    ) {}
}
