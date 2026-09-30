<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Attendance\AttendanceRecomputer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recompute one employee-day. Dispatch with RecomputeAttendanceDay::debounced() so a burst of punches
 * for the same day (spec §6: 30 s debounce) produces a single run.
 * Tenant context is restored in the worker by stancl's QueueTenancyBootstrapper.
 */
final class RecomputeAttendanceDay implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public function __construct(public readonly int $employeeId, public readonly string $date) {}

    public static function debounced(int $employeeId, string $date): void
    {
        self::dispatch($employeeId, $date)->delay(now()->addSeconds(30));
    }

    /** Includes the tenant: the unique-lock cache is shared across tenants. */
    public function uniqueId(): string
    {
        return tenant('id') . ':' . $this->employeeId . ':' . $this->date;
    }

    public function handle(AttendanceRecomputer $recomputer): void
    {
        $recomputer->recompute($this->employeeId, $this->date);
    }
}
