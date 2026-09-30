<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Central: multi-company owner links (spec §9 phase 4) and billing (spec §4.1: subscriptions, invoices, payments). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_links', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('tenant_id');
            $table->timestamps();
            $table->unique(['email', 'tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('plan_id')->constrained('plans');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->string('status', 12)->default('active'); // active|cancelled
            $table->string('billing_cycle', 10)->default('monthly');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('number')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('employee_count');
            $table->unsignedInteger('amount'); // integer rupees
            $table->json('lines');
            $table->date('due_on');
            $table->string('status', 10)->default('open'); // open|paid|void
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'period_start']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method', 20); // bank|jazzcash|easypaisa|cash
            $table->string('reference')->nullable();
            $table->string('status', 12)->default('submitted'); // submitted|confirmed|rejected
            $table->string('submitted_by')->nullable(); // tenant user email
            $table->foreignId('confirmed_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'invoices', 'subscriptions', 'tenant_user_links'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
