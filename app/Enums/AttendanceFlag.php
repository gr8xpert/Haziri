<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Flags stored in attendance_days.flags, independent of status (spec §7.2).
 */
enum AttendanceFlag: string
{
    case Late = 'late';
    case EarlyOut = 'early_out';
    case MissingOut = 'missing_out';
    case MissingIn = 'missing_in';
    case AutoClosed = 'auto_closed';
    case ManualPunch = 'manual_punch';
    case GeofenceFail = 'geofence_fail';
    case Overtime = 'overtime';
    case ShortLeave = 'short_leave';
    case SandwichApplied = 'sandwich_applied';
    case ManualOverride = 'manual_override';
    case Exempted = 'exempted';
}
