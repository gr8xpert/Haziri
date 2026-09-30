<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EmployeeStatus;
use App\Jobs\RecomputeAttendanceRange;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Nightly safety net (spec §7.1): recompute the last N days for active employees of the current tenant.
 * Scheduled through `tenants:run`, so it runs once per tenant.
 */
#[Signature('hazri:attendance:recompute-recent {--days=3}')]
#[Description('Queue recomputation of the last N days for all active employees (current tenant)')]
final class RecomputeRecentAttendance extends Command
{
    public function handle(): int
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $today = CarbonImmutable::now($tz);
        $from = $today->subDays(max(1, (int) $this->option('days')))->format('Y-m-d');

        $ids = Employee::query()->where('status', '!=', EmployeeStatus::Left)->pluck('id')->all();
        RecomputeAttendanceRange::dispatchChunked($ids, $from, $today->format('Y-m-d'));

        $this->info(count($ids) . " employees queued for {$from} → {$today->format('Y-m-d')}.");

        return self::SUCCESS;
    }
}
