<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Payroll Full: income tax slabs, provident fund ledger, one-off pay items, final settlements, statutory employee data. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_slabs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tax_year'); // year the tax year starts in: 2025 = July 2025 – June 2026
            $table->unsignedBigInteger('from_amount');  // annual taxable income, rupees
            $table->unsignedBigInteger('to_amount')->nullable(); // null = and above
            $table->unsignedBigInteger('fixed_tax')->default(0);
            $table->decimal('rate_percent', 5, 2)->default(0); // on the amount above from_amount
            $table->timestamps();
            $table->index(['tax_year', 'from_amount']);
        });

        Schema::create('pay_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // pay-cycle month it is paid / deducted in
            $table->string('type', 10); // earning|deduction
            $table->string('label');
            $table->unsignedInteger('amount');
            $table->boolean('taxable')->default(true);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['year', 'month']);
        });

        Schema::create('pf_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->date('entry_date');
            $table->string('type', 12); // contribution|opening|payout
            $table->integer('employee_amount'); // negative for payouts
            $table->integer('employer_amount');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'entry_date']);
        });

        Schema::create('final_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('last_working_day');
            $table->json('lines');
            $table->integer('net');
            $table->string('status', 10)->default('draft'); // draft|final
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalised_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('net'); // taxable income, tax, employer contributions — for YTD and reports
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('ntn', 20)->nullable()->after('cnic');
            $table->boolean('tax_exempt')->default(false)->after('leave_credit');
            $table->boolean('eobi_registered')->default(false)->after('tax_exempt');
            $table->string('eobi_no', 30)->nullable()->after('eobi_registered');
            $table->boolean('social_security_registered')->default(false)->after('eobi_no');
            $table->string('social_security_no', 30)->nullable()->after('social_security_registered');
            $table->boolean('pf_member')->default(false)->after('social_security_no');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('encashable')->default(false)->after('allow_negative_balance'); // paid out in final settlement
        });

        // Salaried individuals, tax year 2025-26 (Finance Act 2025). Pre-filled for convenience — verify with FBR.
        $now = now();
        foreach ([[0, 600000, 0, 0], [600000, 1200000, 0, 1], [1200000, 2200000, 6000, 11], [2200000, 3200000, 116000, 23],
            [3200000, 4100000, 346000, 30], [4100000, null, 616000, 35]] as [$from, $to, $fixed, $rate]) {
            DB::table('tax_slabs')->insert(['tax_year' => 2025, 'from_amount' => $from, 'to_amount' => $to, 'fixed_tax' => $fixed,
                'rate_percent' => $rate, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_types', fn (Blueprint $table) => $table->dropColumn('encashable'));
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['ntn', 'tax_exempt', 'eobi_registered', 'eobi_no',
            'social_security_registered', 'social_security_no', 'pf_member']));
        Schema::table('payslips', fn (Blueprint $table) => $table->dropColumn('meta'));
        Schema::dropIfExists('final_settlements');
        Schema::dropIfExists('pf_ledger');
        Schema::dropIfExists('pay_items');
        Schema::dropIfExists('tax_slabs');
    }
};
