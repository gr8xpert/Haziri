<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('price_per_employee')->default(0); // integer rupees
            $table->unsignedInteger('price_fixed')->default(0);
            $table->json('feature_flags')->nullable();
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary(); // ULID
            $table->string('slug')->unique();
            $table->string('company_name');
            $table->string('timezone')->default('Asia/Karachi');
            $table->string('status')->default('trial');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('employee_limit')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};
