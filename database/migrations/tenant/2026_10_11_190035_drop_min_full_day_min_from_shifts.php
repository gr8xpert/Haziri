<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Min full day" duplicated "Half day if worked less than" and was never read; the half-day field is the one rule. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shifts', 'min_full_day_min')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->dropColumn('min_full_day_min');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_full_day_min')->default(480)->after('half_day_if_worked_less_than_min');
        });
    }
};
