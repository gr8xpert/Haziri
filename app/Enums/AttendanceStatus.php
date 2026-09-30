<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Resolved status of one employee-day (spec §7.2).
 */
enum AttendanceStatus: string
{
    case NotJoined = 'not_joined';
    case Left = 'left';
    case Holiday = 'holiday';
    case WeeklyOff = 'weekly_off';
    case LeavePaid = 'leave_paid';
    case LeaveUnpaid = 'leave_unpaid';
    case HalfDayLeave = 'half_day_leave';
    case OfficialDuty = 'official_duty';
    case Wfh = 'wfh';
    case Present = 'present';
    case HalfDay = 'half_day';
    case Absent = 'absent';

    /** Short code used in monthly grids (spec §10). Late-present ("L") and leave-type codes are decided by the report layer. */
    public function code(): string
    {
        return match ($this) {
            self::NotJoined, self::Left => '–',
            self::Holiday => 'H',
            self::WeeklyOff => 'WO',
            self::LeavePaid => 'LV',
            self::LeaveUnpaid => 'UL',
            self::HalfDayLeave => 'HL',
            self::OfficialDuty => 'OD',
            self::Wfh => 'WFH',
            self::Present => 'P',
            self::HalfDay => 'HD',
            self::Absent => 'A',
        };
    }
}
