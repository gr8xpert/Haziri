<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

use Carbon\CarbonImmutable;

/**
 * Layers overrides (Ramadan timings, Jummah break…) on top of the assigned shift for one date.
 * Lower priority is applied first, so the highest priority wins on conflicting fields.
 */
final class ShiftOverrideApplier
{
    private const ALLOWED = [
        'startTime', 'endTime', 'crossesMidnight', 'graceInMin', 'graceOutMin', 'lateAfterMin',
        'halfDayIfLateAfterMin', 'halfDayIfWorkedLessThanMin', 'minFullDayMin', 'earlyOutBeforeMin',
        'autoCloseMissingOutAfterMin', 'breakMin', 'overtimeAfterMin', 'maxOvertimeMin',
    ];

    /**
     * @param  list<ShiftOverrideInput>  $overrides
     */
    public function apply(ResolvedShift $shift, string $date, string $timezone, array $overrides): ResolvedShift
    {
        $weekday = (int) CarbonImmutable::parse($date, $timezone)->dayOfWeek;
        $applicable = array_filter($overrides, fn (ShiftOverrideInput $o) => $o->weekday === null || $o->weekday === $weekday);
        usort($applicable, fn (ShiftOverrideInput $a, ShiftOverrideInput $b) => $a->priority <=> $b->priority);

        foreach ($applicable as $override) {
            $changes = array_intersect_key($override->changes, array_flip(self::ALLOWED));
            $shift = $shift->with($changes + ['overrideId' => $override->id]);
        }

        return $shift;
    }
}
