<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;

/** Grid codes (spec §10): P, L, HD, A, WO, H, OD, WFH, LV / leave-type code, UL, MO, –. */
final class StatusCodes
{
    /**
     * @param  array<int, string>  $leaveCodes  leave_request_id => leave type code (CL/SL/AL)
     */
    public static function for(?AttendanceDay $day, array $leaveCodes = []): string
    {
        if ($day === null || $day->status === null) {
            return '';
        }
        $flags = $day->flags ?? [];

        return match ($day->status) {
            AttendanceStatus::Present => in_array('missing_out', $flags, true) && ! in_array('auto_closed', $flags, true)
                ? 'MO' : (in_array('late', $flags, true) ? 'L' : 'P'),
            AttendanceStatus::LeavePaid, AttendanceStatus::HalfDayLeave => ($leaveCodes[$day->leave_request_id] ?? 'LV')
                . ($day->status === AttendanceStatus::HalfDayLeave ? '½' : ''),
            default => $day->status->code(),
        };
    }

    /** @return array<string, string> legend for report footers */
    public static function legend(): array
    {
        return [
            'P' => 'Present', 'L' => 'Late', 'HD' => 'Half day', 'A' => 'Absent', 'WO' => 'Weekly off', 'H' => 'Holiday',
            'OD' => 'Official duty', 'WFH' => 'Work from home', 'LV/CL/SL/AL' => 'Paid leave', 'UL' => 'Unpaid leave',
            'MO' => 'Missing out', 'EX' => 'Exempted', '–' => 'Not joined / left',
        ];
    }
}
