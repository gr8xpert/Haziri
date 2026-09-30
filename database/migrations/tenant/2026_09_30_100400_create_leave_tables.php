<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §5.6 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique(); // CL, SL, AL — also the grid code
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_quota_days', 5, 1)->default(0);
            $table->string('accrual', 16)->default('yearly_upfront'); // yearly_upfront|monthly
            $table->decimal('carry_forward_max', 5, 1)->default(0);
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->boolean('allow_half_day')->default(true);
            $table->decimal('allow_short_leave_hours', 4, 1)->nullable();
            $table->unsignedSmallInteger('requires_attachment_after_days')->nullable();
            $table->boolean('sandwich_rule')->default(false);
            $table->json('applies_to')->nullable(); // employment types; null = all
            $table->string('gender_restriction', 10)->nullable();
            $table->boolean('counts_weekends')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('opening', 5, 1)->default(0);
            $table->decimal('accrued', 5, 1)->default(0);
            $table->decimal('used', 5, 1)->default(0);
            $table->decimal('pending', 5, 1)->default(0);
            $table->decimal('adjusted', 5, 1)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('day_part', 12)->default('full');
            $table->time('short_leave_from')->nullable();
            $table->time('short_leave_to')->nullable();
            $table->decimal('days', 5, 1);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status', 10)->default('pending');
            $table->json('approver_chain')->nullable();
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->json('sandwich_dates')->nullable(); // extra off-days pulled in by the sandwich rule
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decided_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'from_date', 'to_date', 'status']);
        });

        Schema::create('leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 5, 1); // + or −
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_adjustments');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
