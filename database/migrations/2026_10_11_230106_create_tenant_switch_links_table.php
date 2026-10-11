<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central: "Switch company" pairs. A login may switch from tenant_id to target_tenant_id only when this exact pair
 * exists, written both ways. Existing owner links (tenant_user_links, made at provisioning) become pairs between the
 * companies provisioned for the same email, so they keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_switch_links', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('tenant_id');
            $table->string('target_tenant_id');
            $table->timestamps();
            $table->unique(['email', 'tenant_id', 'target_tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('target_tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        $now = now();
        foreach (DB::table('tenant_user_links')->get(['email', 'tenant_id'])->groupBy('email') as $email => $links) {
            foreach ($links as $from) {
                foreach ($links as $to) {
                    if ($from->tenant_id !== $to->tenant_id) {
                        DB::table('tenant_switch_links')->insertOrIgnore([
                            'email' => $email, 'tenant_id' => $from->tenant_id, 'target_tenant_id' => $to->tenant_id,
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_switch_links');
    }
};
