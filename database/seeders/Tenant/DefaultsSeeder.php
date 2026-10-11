<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Default shift, leave types (CL/SL/AL) and rule settings for a new tenant (spec §4.2, §15).
 * Idempotent — existing rows are left alone.
 */
class DefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now();

        DB::table('shifts')->insertOrIgnore([
            'name' => 'General (09:00–18:00)',
            'code' => 'GEN',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'grace_in_min' => 15,
            'grace_out_min' => 5,
            'half_day_if_late_after_min' => 120,
            'half_day_if_worked_less_than_min' => 240,
            'break_min' => 60,
            'weekly_off_days' => json_encode([0]),
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $leaveTypes = [
            ['name' => 'Casual Leave', 'code' => 'CL', 'annual_quota_days' => 10, 'allow_half_day' => true, 'sandwich_rule' => false],
            ['name' => 'Sick Leave', 'code' => 'SL', 'annual_quota_days' => 8, 'allow_half_day' => true, 'sandwich_rule' => false, 'requires_attachment_after_days' => 2],
            ['name' => 'Annual Leave', 'code' => 'AL', 'annual_quota_days' => 14, 'allow_half_day' => false, 'sandwich_rule' => true, 'carry_forward_max' => 7],
        ];
        foreach ($leaveTypes as $type) {
            DB::table('leave_types')->insertOrIgnore($type + ['is_paid' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        if (! DB::table('separation_reasons')->exists()) {
            foreach (['Resigned', 'Terminated', 'Lay-off', 'Retired', 'Contract ended'] as $reason) {
                DB::table('separation_reasons')->insert(['name' => $reason, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach (config('hazri.settings_defaults') as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'value' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
