<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Spec §11: templates per event/channel/locale, delivery log, and Laravel's in-app notifications table. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50);
            $table->string('channel', 20); // mail|database|whatsapp|sms
            $table->string('locale', 5)->default('en');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('provider_template')->nullable(); // e.g. approved WhatsApp template name
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['event', 'channel', 'locale']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50);
            $table->string('channel', 20);
            $table->string('recipient');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 10); // queued|sent|failed|skipped
            $table->string('provider_ref')->nullable();
            $table->text('error')->nullable();
            $table->string('dedupe_key')->nullable()->index(); // time-based events send once per key
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['event', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};
