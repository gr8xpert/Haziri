<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §5.1 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius_m')->default(150);
            $table->string('timezone')->nullable(); // null → tenant timezone
            $table->json('weekly_off_days')->nullable(); // null → [0] (Sunday)
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('grade', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->unsignedBigInteger('head_employee_id')->nullable(); // FK added below
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('cnic', 13)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('dob')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employment_type', 20)->default('permanent');
            $table->date('joined_on');
            $table->date('probation_ends_on')->nullable();
            $table->date('contract_ends_on')->nullable();
            $table->date('left_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('device_pin', 20)->nullable()->unique();
            $table->string('card_no', 30)->nullable();
            $table->string('kiosk_pin')->nullable(); // hashed 4-digit PIN for kiosk mode
            $table->boolean('overtime_eligible')->default(false);
            $table->boolean('late_deduction_exempt')->default(false);
            $table->string('work_mode', 10)->default('office');
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'branch_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['employee_id']));
        Schema::table('departments', fn (Blueprint $t) => $t->dropForeign(['head_employee_id']));
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('settings');
    }
};
