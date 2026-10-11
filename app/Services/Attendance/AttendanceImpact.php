<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\EmployeeStatus;
use App\Enums\PeriodStatus;
use App\Jobs\RecomputeAttendanceDay;
use App\Jobs\RecomputeAttendanceRange;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\Scopes\BranchScope;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Turns "something attendance-related changed" into recompute jobs, clamped to a sane window: never the future.
 * A change for named employees (a correction, leave, a punch) is recomputed however old the day is; the recomputer
 * itself leaves locked months alone. A company- or branch-wide change reaches back LOOKBACK_DAYS, or further to the
 * first day after the latest locked month, so no open month is skipped.
 */
final class AttendanceImpact
{
    public const LOOKBACK_DAYS = 62;

    /**
     * @param  list<int>|null  $employeeIds  null = all non-left employees (optionally in $branchId)
     */
    public static function dispatch(?array $employeeIds, string $from, ?string $to = null, ?int $branchId = null): void
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $today = CarbonImmutable::now($tz)->format('Y-m-d');
        if ($employeeIds === null) {
            $from = max($from, self::companyWideFloor($tz));
        }
        $to = min($to ?? $today, $today);
        if ($from > $to) {
            return;
        }

        $ids = $employeeIds ?? Employee::query()->withoutGlobalScope(BranchScope::class)
            ->where('status', '!=', EmployeeStatus::Left)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        if ($employeeIds !== null) {
            $joined = Employee::query()->withoutGlobalScope(BranchScope::class)->whereIn('id', $ids)->min('joined_on');
            $from = $joined !== null ? max($from, substr((string) $joined, 0, 10)) : $from;
            if ($from > $to) {
                return;
            }
        }

        $days = iterator_to_array(CarbonPeriod::create($from, $to));
        if (count($ids) === 1 && count($days) <= 3) {
            foreach ($days as $d) {
                RecomputeAttendanceDay::debounced($ids[0], $d->format('Y-m-d'));
            }

            return;
        }

        RecomputeAttendanceRange::dispatchChunked($ids, $from, $to);
    }

    /** LOOKBACK_DAYS ago, or the first day after the latest locked month when that is earlier. */
    private static function companyWideFloor(string $tz): string
    {
        $floor = CarbonImmutable::now($tz)->subDays(self::LOOKBACK_DAYS)->format('Y-m-d');
        $latestLocked = AttendancePeriod::query()->where('status', PeriodStatus::Locked)
            ->orderByDesc('year')->orderByDesc('month')->first();
        if ($latestLocked === null) {
            return $floor;
        }
        $afterLock = CarbonImmutable::parse($latestLocked->dates()[1])->addDay()->format('Y-m-d');

        return min($floor, $afterLock);
    }
}
