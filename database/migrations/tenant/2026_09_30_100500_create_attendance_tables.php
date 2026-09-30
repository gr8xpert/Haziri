<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §5.5 + §5.7 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 8)->default('open');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('attendance_markers', function (Blueprint $table) {
            // OD / WFH days set by HR or by an approved correction. Read by the status engine.
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 16); // official_duty|wfh
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 16)->nullable(); // null = pending (day not over yet)
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('override_id')->nullable()->constrained('shift_overrides')->nullOnDelete();
            $table->foreignId('holiday_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('first_in')->nullable();
            $table->dateTime('last_out')->nullable();
            $table->unsignedSmallInteger('worked_min')->default(0);
            $table->unsignedSmallInteger('break_min')->default(0);
            $table->unsignedSmallInteger('late_min')->default(0);
            $table->unsignedSmallInteger('early_out_min')->default(0);
            $table->unsignedSmallInteger('overtime_min')->default(0);
            $table->unsignedSmallInteger('expected_min')->default(0);
            $table->json('flags')->nullable();
            $table->json('source_summary')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->unsignedSmallInteger('computed_version')->default(1);
            $table->boolean('is_locked')->default(false);
            $table->foreignId('locked_by_period_id')->nullable()->constrained('attendance_periods')->nullOnDelete();
            $table->json('manual_override')->nullable(); // {status, reason, user_id, at}
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 16);
            $table->json('payload')->nullable();
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status', 10)->default('pending');
            $table->json('approver_chain')->nullable();
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('attendance_markers');
        Schema::dropIfExists('attendance_periods');
    }
};
