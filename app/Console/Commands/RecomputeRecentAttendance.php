<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EmployeeStatus;
use App\Jobs\RecomputeAttendanceRange;
use App\Models\Employee;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Nightly safety net (spec §7.1): recompute the last N days for active employees of the current tenant.
 * Reaches back at least "Mark absent after (days)" + 1, so a day waiting to become absent is recomputed once it may.
 * Scheduled through `tenants:run`, so it runs once per tenant.
 */
#[Signature('hazri:attendance:recompute-recent {--days=3}')]
#[Description('Queue recomputation of the last N days for all active employees (current tenant)')]
final class RecomputeRecentAttendance extends Command
{
    public function handle(TenantSettings $settings): int
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $today = CarbonImmutable::now($tz);
        $days = max(1, (int) $this->option('days'), (int) $settings->get('auto_absent_after_days') + 1);
        $from = $today->subDays($days)->format('Y-m-d');

        $ids = Employee::query()->where('status', '!=', EmployeeStatus::Left)->pluck('id')->all();
        RecomputeAttendanceRange::dispatchChunked($ids, $from, $today->format('Y-m-d'));

        $this->info(count($ids) . " employees queued for {$from} → {$today->format('Y-m-d')}.");

        return self::SUCCESS;
    }
}
