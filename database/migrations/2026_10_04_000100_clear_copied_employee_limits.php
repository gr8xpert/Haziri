<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Central: a company's employee_limit is now only a hand-set override; blank means "the plan's limit". Companies used
 * to get the plan's number copied in at creation, which then outlived plan changes, so clear the copies.
 */
return new class extends Migration
{
    public function up(): void
    {
        $planLimits = DB::table('plans')->pluck('limits', 'id')
            ->map(fn (?string $limits) => json_decode((string) $limits, true)['employees'] ?? null);

        foreach (DB::table('tenants')->whereNotNull('plan_id')->whereNotNull('employee_limit')->get(['id', 'plan_id', 'employee_limit']) as $tenant) {
            if ((int) $tenant->employee_limit === (int) ($planLimits[$tenant->plan_id] ?? -1)) {
                DB::table('tenants')->where('id', $tenant->id)->update(['employee_limit' => null]);
            }
        }
    }

    public function down(): void
    {
        // Copies are not restored: blank already means the plan's limit.
    }
};
