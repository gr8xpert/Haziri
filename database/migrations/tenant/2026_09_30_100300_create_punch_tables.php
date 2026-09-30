<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §5.4 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_pin', 20)->nullable();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('punched_at'); // UTC
            $table->date('local_date');
            $table->string('direction', 8)->default('unknown');
            $table->string('source', 8);
            $table->string('verify_type', 10)->default('none');
            $table->string('workcode', 20)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('external_id', 50)->nullable();
            $table->char('dedupe_hash', 40)->unique();
            $table->boolean('is_manual')->default(false);
            $table->string('reason')->nullable();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('valid');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'local_date']);
            $table->index(['device_id', 'punched_at']);
            $table->index(['status', 'device_pin']);
        });

        Schema::create('unmapped_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('device_pin', 20);
            $table->string('device_user_name')->nullable(); // from ADMS USERINFO / agent get_users()
            $table->timestamp('first_seen_at');
            $table->unsignedInteger('count')->default(0);
            $table->foreignId('resolved_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->unique(['device_id', 'device_pin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmapped_pins');
        Schema::dropIfExists('punches');
    }
};
