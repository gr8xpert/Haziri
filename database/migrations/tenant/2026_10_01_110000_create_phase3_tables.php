<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §5.8 (payroll-lite) and §5.9 (announcements, visitors). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('audience')->nullable(); // {branch_ids:[], department_ids:[]} — null = everyone
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('requires_ack')->default(false);
            $table->boolean('notify')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_acks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acked_at');
            $table->unique(['announcement_id', 'employee_id']);
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('cnic', 13)->nullable();
            $table->string('company')->nullable();
            $table->foreignId('host_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->string('badge_no', 20)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('checked_in_at');
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->unsignedInteger('basic')->default(0);           // integer rupees
            $table->json('allowances')->nullable();                 // [{name, amount}]
            $table->json('deductions_fixed')->nullable();           // [{name, amount}]
            $table->unsignedInteger('per_day_rate')->nullable();    // daily wage
            $table->unsignedInteger('overtime_rate_normal')->nullable();  // per hour
            $table->unsignedInteger('overtime_rate_holiday')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->date('issued_on');
            $table->unsignedTinyInteger('instalments')->default(1);
            $table->unsignedInteger('remaining');
            $table->string('status', 10)->default('active'); // active|settled
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('attendance_periods')->restrictOnDelete();
            $table->string('status', 10)->default('draft'); // draft|final
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('totals')->nullable();
            $table->timestamps();
            $table->unique('period_id');
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->json('lines');
            $table->integer('gross');
            $table->integer('deductions');
            $table->integer('net');
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        foreach (['payslips', 'payroll_runs', 'advances', 'salary_structures', 'visitors', 'announcement_acks', 'announcements'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
