<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Flags stored in attendance_days.flags, independent of status (spec §7.2).
 */
enum AttendanceFlag: string implements HasLabel
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
    /** A holiday the company does not pay for (Holiday "Paid" off). */
    case UnpaidHoliday = 'unpaid_holiday';

    public function getLabel(): string
    {
        return match ($this) {
            self::Late => __('Late'),
            self::EarlyOut => __('Left early'),
            self::MissingOut => __('Missing out punch'),
            self::MissingIn => __('Missing in punch'),
            self::AutoClosed => __('Auto-closed'),
            self::ManualPunch => __('Manual punch'),
            self::GeofenceFail => __('Outside office area'),
            self::Overtime => __('Overtime'),
            self::ShortLeave => __('Short leave'),
            self::SandwichApplied => __('Sandwich rule applied'),
            self::ManualOverride => __('Changed by hand'),
            self::Exempted => __('Exempted'),
            self::UnpaidHoliday => __('Unpaid holiday'),
        };
    }
}
