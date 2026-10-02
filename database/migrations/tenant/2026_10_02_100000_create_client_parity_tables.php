<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parity with the client's legacy TMS: grades, contractors, separation reasons, company-wide exemptions,
 * grade-wise leave entitlements, extra employee master fields and late/half-day leave adjustments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('separation_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_exemptions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('type', 10); // late|absent
            $table->string('reason');
            $table->string('scope', 12)->default('tenant'); // tenant|branch|department
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('date');
        });

        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('employment_type', 20)->nullable(); // null = every category
            $table->decimal('days_per_year', 5, 1);
            $table->timestamps();
            $table->unique(['leave_type_id', 'grade_id', 'employment_type']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('father_name')->nullable()->after('last_name');
            $table->text('address')->nullable()->after('dob');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('home_phone', 20)->nullable()->after('phone');
            $table->string('office_phone', 20)->nullable()->after('home_phone');
            $table->string('office_ext', 10)->nullable()->after('office_phone');
            $table->string('official_mobile', 20)->nullable()->after('office_ext');
            $table->string('emergency_contact')->nullable()->after('official_mobile');
            $table->string('payroll_id', 30)->nullable()->after('code');
            $table->foreignId('grade_id')->nullable()->after('designation_id')->constrained()->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->after('grade_id')->constrained()->nullOnDelete();
            $table->foreignId('separation_reason_id')->nullable()->after('left_on')->constrained()->nullOnDelete();
            $table->boolean('leave_credit')->default(true)->after('late_deduction_exempt');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('allow_negative_balance')->default(false)->after('carry_forward_max');
        });

        Schema::table('leave_adjustments', function (Blueprint $table) {
            $table->date('effective_on')->nullable()->after('days');
            $table->foreignId('attendance_period_id')->nullable()->after('effective_on')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_adjustments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_period_id');
            $table->dropColumn('effective_on');
        });
        Schema::table('leave_types', fn (Blueprint $table) => $table->dropColumn('allow_negative_balance'));
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
            $table->dropConstrainedForeignId('contractor_id');
            $table->dropConstrainedForeignId('separation_reason_id');
            $table->dropColumn(['father_name', 'address', 'city', 'home_phone', 'office_phone', 'office_ext',
                'official_mobile', 'emergency_contact', 'payroll_id', 'leave_credit']);
        });
        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('attendance_exemptions');
        Schema::dropIfExists('separation_reasons');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('grades');
    }
};
