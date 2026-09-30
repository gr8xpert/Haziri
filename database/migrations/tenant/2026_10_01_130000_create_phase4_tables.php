<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §9 phase 4 + §12 + §13.3: shift swaps, anomalies, AI query log, public API tokens, webhooks. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('partner_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // buddy_punching|ghost_employee|pattern_shift|impossible_sequence
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('detected_on');
            $table->json('evidence');
            $table->text('explanation')->nullable(); // one line, AI-written when enabled, template otherwise
            $table->string('status', 12)->default('open'); // open|investigating|dismissed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dedupe_key')->unique();
            $table->timestamps();
        });

        Schema::create('ai_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 30); // ask|narrative|anomaly|attachment|setup
            $table->text('prompt');
            $table->text('generated_sql')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->string('status', 12);
            $table->text('error')->nullable();
            $table->tinyInteger('feedback')->nullable(); // 1 / -1
            $table->timestamps();
            $table->index(['feature', 'created_at']);
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->json('abilities'); // read|punches:write
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->text('secret'); // encrypted
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('status', 10)->default('pending'); // pending|delivered|failed
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('attendance_periods', function (Blueprint $table) {
            $table->json('narrative')->nullable()->after('summary'); // {en, ur, generated_at, edited_by}
        });
    }

    public function down(): void
    {
        Schema::table('attendance_periods', fn (Blueprint $t) => $t->dropColumn('narrative'));
        foreach (['webhook_deliveries', 'webhook_endpoints', 'api_tokens', 'ai_queries', 'anomalies', 'shift_swap_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
