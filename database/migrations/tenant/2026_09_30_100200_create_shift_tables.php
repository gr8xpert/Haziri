<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §5.3 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('crosses_midnight')->default(false);
            $table->unsignedSmallInteger('grace_in_min')->default(15);
            $table->unsignedSmallInteger('grace_out_min')->default(5);
            $table->unsignedSmallInteger('late_after_min')->nullable();
            $table->unsignedSmallInteger('half_day_if_late_after_min')->nullable()->default(120);
            $table->unsignedSmallInteger('half_day_if_worked_less_than_min')->nullable()->default(240);
            $table->unsignedSmallInteger('min_full_day_min')->default(480);
            $table->unsignedSmallInteger('early_out_before_min')->default(30);
            $table->unsignedSmallInteger('auto_close_missing_out_after_min')->nullable();
            $table->unsignedSmallInteger('break_min')->default(60);
            $table->string('multi_punch_mode', 12)->default('first_last');
            $table->unsignedSmallInteger('overtime_after_min')->nullable();
            $table->unsignedSmallInteger('max_overtime_min')->nullable();
            $table->json('weekly_off_days')->nullable(); // null → branch weekly offs
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('shift_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Ramadan 2027", "Jummah"
            $table->date('from_date');
            $table->date('to_date');
            $table->string('scope', 12)->default('tenant'); // tenant|branch|department|shift
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable(); // 5 = Friday (Jummah)
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('grace_in_min')->nullable();
            $table->unsignedSmallInteger('grace_out_min')->nullable();
            $table->unsignedSmallInteger('late_after_min')->nullable();
            $table->unsignedSmallInteger('half_day_if_late_after_min')->nullable();
            $table->unsignedSmallInteger('half_day_if_worked_less_than_min')->nullable();
            $table->unsignedSmallInteger('break_min')->nullable();
            $table->unsignedSmallInteger('priority')->default(10);
            $table->timestamps();
            $table->index(['from_date', 'to_date']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('type', 10)->default('gazetted');
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete(); // null = all branches
            $table->boolean('is_paid')->default(true);
            $table->boolean('needs_confirmation')->default(false); // provisional (moon-sighting) dates
            $table->timestamps();
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('shift_overrides');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
    }
};
