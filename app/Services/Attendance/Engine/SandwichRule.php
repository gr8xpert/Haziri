<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

use Carbon\CarbonImmutable;

/**
 * Sandwich leave rule (spec §7.3): weekly offs / holidays that sit between two leave days
 * are counted as leave too. Applied when leave is approved, not inside the day engine.
 */
final class SandwichRule
{
    /**
     * @param  list<string>  $leaveDates  Y-m-d dates the employee is on (sandwich-rule) leave
     * @param  callable(string): bool  $isNonWorkingDay  true for weekly off or holiday
     * @return list<string> non-working dates that become leave, sorted
     */
    public function sandwichedDates(array $leaveDates, callable $isNonWorkingDay): array
    {
        $leaveDates = array_values(array_unique($leaveDates));
        sort($leaveDates);
        $leaveSet = array_flip($leaveDates);
        $result = [];

        for ($i = 0; $i + 1 < count($leaveDates); $i++) {
            $cursor = CarbonImmutable::parse($leaveDates[$i])->addDay();
            $next = $leaveDates[$i + 1];
            $gap = [];
            while ($cursor->toDateString() < $next) {
                $d = $cursor->toDateString();
                if (isset($leaveSet[$d]) || ! $isNonWorkingDay($d)) {
                    // A working day in between breaks the sandwich.
                    $gap = null;
                    break;
                }
                $gap[] = $d;
                $cursor = $cursor->addDay();
            }
            if ($gap) {
                array_push($result, ...$gap);
            }
        }

        return $result;
    }
}
