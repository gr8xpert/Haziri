<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stop punching from home: kiosk tablets approved one by one, office-IP restrictions, per-employee mobile switch. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosks', function (Blueprint $table) {
            $table->boolean('lock_to_device')->default(false)->after('require_photo'); // existing kiosks keep working
            $table->json('allowed_ips')->nullable()->after('lock_to_device');
        });

        Schema::create('kiosk_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kiosk_id')->constrained()->cascadeOnDelete();
            $table->char('key_hash', 64);
            $table->string('pairing_code', 8);
            $table->string('label')->nullable(); // browser / device description
            $table->string('status', 10)->default('pending'); // pending|approved|blocked
            $table->string('ip', 45)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['kiosk_id', 'key_hash']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->json('allowed_ips')->nullable()->after('geofence_radius_m'); // office internet; mobile punches elsewhere need approval
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('mobile_punch_allowed')->default(true)->after('work_mode');
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('mobile_punch_allowed'));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('allowed_ips'));
        Schema::dropIfExists('kiosk_devices');
        Schema::table('kiosks', fn (Blueprint $table) => $table->dropColumn(['lock_to_device', 'allowed_ips']));
    }
};
