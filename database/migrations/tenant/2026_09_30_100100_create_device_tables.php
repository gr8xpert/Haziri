<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §5.2 (+ device_commands for ADMS / agent remote commands, §6.1–6.2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('serial_number', 50)->unique();
            $table->string('model', 50)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10); // adms|agent|usb|kiosk|manual|mobile
            $table->string('comm_key')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('timezone')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->unsignedInteger('unsynced_count')->default(0);
            $table->string('status', 10)->default('unknown');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('command', 30); // set_user|delete_user|sync_time|reboot|check
            $table->json('payload')->nullable();
            $table->string('status', 10)->default('pending'); // pending|sent|done|failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'status']);
        });

        Schema::create('kiosks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->json('allowed_modes')->nullable(); // pin, qr, face
            $table->boolean('require_photo')->default(true);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('usb_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_duplicate')->default(0);
            $table->unsignedInteger('rows_unmapped')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usb_imports');
        Schema::dropIfExists('kiosks');
        Schema::dropIfExists('device_commands');
        Schema::dropIfExists('devices');
    }
};
