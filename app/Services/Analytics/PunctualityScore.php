<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceDay;
use Illuminate\Support\Collection;

/**
 * Punctuality score 0–100 (spec §9 phase 4), over working days only (present / half day / absent):
 *   100 − 50 × late share − 75 × half-day share − 100 × absent share, clamped to 0..100.
 * An employee who is on time every working day scores 100; late every day scores 50; absent every day 0.
 */
final class PunctualityScore
{
    /**
     * @param  Collection<int, AttendanceDay>  $days
     * @return array{score: int, working: int, late: int, half: int, absent: int}
     */
    public static function of(Collection $days): array
    {
        $working = $days->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::HalfDay, AttendanceStatus::Absent]);
        $n = $working->count();
        if ($n === 0) {
            return ['score' => 100, 'working' => 0, 'late' => 0, 'half' => 0, 'absent' => 0];
        }
        $late = $working->filter(fn (AttendanceDay $d) => in_array('late', $d->flags ?? [], true))->count();
        $half = $working->where('status', AttendanceStatus::HalfDay)->count();
        $absent = $working->where('status', AttendanceStatus::Absent)->count();
        $score = 100 - 50 * $late / $n - 75 * $half / $n - 100 * $absent / $n;

        return ['score' => (int) round(max(0, min(100, $score))), 'working' => $n, 'late' => $late, 'half' => $half, 'absent' => $absent];
    }
}
