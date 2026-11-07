<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Central: no plan or company caps the number of employees any more (blank limit = unlimited). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('plans')->get(['id', 'limits']) as $plan) {
            $limits = json_decode((string) $plan->limits, true) ?: [];
            if (($limits['employees'] ?? null) !== null) {
                $limits['employees'] = null;
                DB::table('plans')->where('id', $plan->id)->update(['limits' => json_encode($limits)]);
            }
        }

        DB::table('tenants')->whereNotNull('employee_limit')->update(['employee_limit' => null]);
    }

    public function down(): void
    {
        // The old limits are not restored.
    }
};
