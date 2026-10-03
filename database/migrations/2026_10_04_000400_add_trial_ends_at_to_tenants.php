<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Central: trials end after hazri.billing.trial_days; companies already on trial get a full trial from today. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('employee_limit');
        });

        DB::table('tenants')->where('status', 'trial')->update(['trial_ends_at' => now()->addDays((int) config('hazri.billing.trial_days', 15))]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });
    }
};
