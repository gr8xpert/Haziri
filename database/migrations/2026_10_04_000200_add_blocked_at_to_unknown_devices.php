<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Central: the super-admin can block a stray machine so it is turned away without being recorded. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unknown_devices', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('hits');
        });
    }

    public function down(): void
    {
        Schema::table('unknown_devices', function (Blueprint $table) {
            $table->dropColumn('blocked_at');
        });
    }
};
