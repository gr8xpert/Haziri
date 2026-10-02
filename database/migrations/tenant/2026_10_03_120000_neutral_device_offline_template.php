<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** White-label: the seeded "device offline" message named the platform; make existing copies neutral. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_templates')->where('event', 'device_offline')->where('body', 'like', '%has not contacted Hazri since%')
            ->update(['body' => DB::raw("REPLACE(body, 'has not contacted Hazri since', 'has sent nothing since')")]);
    }

    public function down(): void {}
};
