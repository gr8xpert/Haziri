<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Grade / category rows can also set their own carry-forward cap and longest request (blank = the leave type's). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_entitlements', function (Blueprint $table) {
            $table->decimal('carry_forward_max', 5, 1)->nullable()->after('days_per_year');
            $table->unsignedSmallInteger('max_consecutive_days')->nullable()->after('carry_forward_max');
        });
    }

    public function down(): void
    {
        Schema::table('leave_entitlements', function (Blueprint $table) {
            $table->dropColumn(['carry_forward_max', 'max_consecutive_days']);
        });
    }
};
