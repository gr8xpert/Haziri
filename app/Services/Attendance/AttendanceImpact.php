<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\EmployeeStatus;
use App\Jobs\RecomputeAttendanceDay;
use App\Jobs\RecomputeAttendanceRange;
use App\Models\Employee;
use App\Models\Scopes\BranchScope;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Turns "something attendance-related changed" into recompute jobs, clamped to a sane window:
 * never the future, never older than LOOKBACK_DAYS (older months are normally locked anyway).
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
        $floor = CarbonImmutable::now($tz)->subDays(self::LOOKBACK_DAYS)->format('Y-m-d');

        $from = max($from, $floor);
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

        $days = iterator_to_array(CarbonPeriod::create($from, $to));
        if (count($ids) === 1 && count($days) <= 3) {
            foreach ($days as $d) {
                RecomputeAttendanceDay::debounced($ids[0], $d->format('Y-m-d'));
            }

            return;
        }

        RecomputeAttendanceRange::dispatchChunked($ids, $from, $to);
    }
}
