<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Resolved status of one employee-day (spec §7.2).
 */
enum AttendanceStatus: string implements HasLabel
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
    /** Absent on a company-wide exempted day (strike, flood…) — excused and paid. */
    case Exempted = 'exempted';

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
            self::Exempted => 'EX',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::NotJoined => __('Not joined yet'),
            self::Left => __('Left'),
            self::Holiday => __('Holiday'),
            self::WeeklyOff => __('Off day'),
            self::LeavePaid => __('Paid leave'),
            self::LeaveUnpaid => __('Unpaid leave'),
            self::HalfDayLeave => __('Half-day leave'),
            self::OfficialDuty => __('Official duty'),
            self::Wfh => __('Work from home'),
            self::Present => __('Present'),
            self::HalfDay => __('Half day'),
            self::Absent => __('Absent'),
            self::Exempted => __('Exempted'),
        };
    }
}
