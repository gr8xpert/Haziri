<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One holiday per date and branch. branch_key mirrors branch_id with 0 for "all branches", because a unique index
 * lets any number of NULL branch_ids through. Existing duplicates are merged into the oldest row first: its
 * opt-ins and attendance-day links move over, then the extra rows go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_key')->default(0)->after('branch_id');
        });
        DB::table('holidays')->whereNotNull('branch_id')->update(['branch_key' => DB::raw('branch_id')]);

        $groups = DB::table('holidays')->select('date', 'branch_key', DB::raw('MIN(id) as keep_id'))
            ->groupBy('date', 'branch_key')->havingRaw('COUNT(*) > 1')->get();
        foreach ($groups as $group) {
            $extraIds = DB::table('holidays')->where('date', $group->date)->where('branch_key', $group->branch_key)
                ->where('id', '!=', $group->keep_id)->pluck('id');
            $keptOptIns = DB::table('holiday_opt_ins')->where('holiday_id', $group->keep_id)->pluck('employee_id');
            DB::table('holiday_opt_ins')->whereIn('holiday_id', $extraIds)->whereNotIn('employee_id', $keptOptIns)
                ->orderBy('id')->get()->unique('employee_id')
                ->each(fn (object $optIn) => DB::table('holiday_opt_ins')->where('id', $optIn->id)->update(['holiday_id' => $group->keep_id]));
            DB::table('attendance_days')->whereIn('holiday_id', $extraIds)->update(['holiday_id' => $group->keep_id]);
            DB::table('holidays')->whereIn('id', $extraIds)->delete();
        }

        Schema::table('holidays', function (Blueprint $table) {
            $table->unique(['date', 'branch_key']);
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['date', 'branch_key']);
            $table->dropColumn('branch_key');
        });
    }
};
