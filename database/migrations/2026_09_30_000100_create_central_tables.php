<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Central DB tables from spec §4.1 needed in phase 1–2. Billing tables come with phase 4. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('device_registry', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('tenant_id');
            $table->string('comm_key')->nullable();
            $table->string('type'); // adms|agent|kiosk
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('unknown_devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('ip', 45)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('hits')->default(1);
            $table->timestamps();
        });

        Schema::create('agent_keys', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('key_hash', 64)->unique(); // sha256 of the bearer key
            $table->string('label');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_keys');
        Schema::dropIfExists('unknown_devices');
        Schema::dropIfExists('device_registry');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('super_admins');
    }
};
