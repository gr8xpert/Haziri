<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Announcements are broadcast when they publish, not when they are saved: remember when that happened. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('notify');
        });

        // Everything that existed before was broadcast on save already.
        DB::table('announcements')->where('notify', true)->update(['notified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
