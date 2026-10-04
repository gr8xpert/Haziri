<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A special leave rule for one employee and leave type; beats grade / category rows and the leave type. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('days_per_year', 5, 1)->nullable();
            $table->decimal('carry_forward_max', 5, 1)->nullable();
            $table->unsignedSmallInteger('max_consecutive_days')->nullable();
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_rules');
    }
};
