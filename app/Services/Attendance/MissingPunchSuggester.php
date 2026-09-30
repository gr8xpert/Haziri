<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\AttendanceDay;
use App\Models\Employee;

/**
 * Spec §12.4 (statistical, no model call): suggest a punch-out time for a missing_out day from the median
 * out time of the employee's last 20 complete days.
 */
final class MissingPunchSuggester
{
    public function suggestOut(Employee $employee, ?string $beforeDate = null): ?string
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $minutes = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('last_out')
            ->when($beforeDate, fn ($q) => $q->where('date', '<', $beforeDate))
            ->orderByDesc('date')
            ->limit(40)
            ->get()
            ->reject(fn (AttendanceDay $d) => array_intersect($d->flags ?? [], ['missing_out', 'auto_closed']) !== [])
            ->take(20)
            ->map(function (AttendanceDay $d) use ($tz) {
                $t = $d->last_out->setTimezone($tz);

                return $t->hour * 60 + $t->minute;
            })
            ->sort()
            ->values();

        if ($minutes->count() < 5) {
            return null; // not enough history to be useful
        }
        $mid = intdiv($minutes->count(), 2);
        $median = $minutes->count() % 2 ? $minutes[$mid] : intdiv($minutes[$mid - 1] + $minutes[$mid], 2);

        return sprintf('%02d:%02d', intdiv($median, 60), $median % 60);
    }
}
