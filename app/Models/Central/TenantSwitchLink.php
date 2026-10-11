<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One "Switch company" pair: the login with this email may switch from tenant_id to target_tenant_id. Only the exact
 * pair counts; links are never followed through a third company and never inferred from a shared email.
 */
class TenantSwitchLink extends Model
{
    use CentralConnection;

    protected $guarded = ['id'];

    public function target(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    /** Writes the pair in both directions. */
    public static function pair(string $email, string $tenantId, string $otherTenantId): void
    {
        $email = strtolower($email);
        static::query()->firstOrCreate(['email' => $email, 'tenant_id' => $tenantId, 'target_tenant_id' => $otherTenantId]);
        static::query()->firstOrCreate(['email' => $email, 'tenant_id' => $otherTenantId, 'target_tenant_id' => $tenantId]);
    }

    public static function allows(string $email, string $fromTenantId, string $toTenantId): bool
    {
        return static::query()->where(['email' => strtolower($email), 'tenant_id' => $fromTenantId, 'target_tenant_id' => $toTenantId])->exists();
    }
}
