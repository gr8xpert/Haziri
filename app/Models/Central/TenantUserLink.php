<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * The owner email a company was provisioned for (spec §9 phase 4). Only provisioning writes these; switching itself
 * reads the pairs in TenantSwitchLink.
 */
class TenantUserLink extends Model
{
    use CentralConnection;

    protected $guarded = ['id'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function link(string $email, string $tenantId): void
    {
        static::query()->firstOrCreate(['email' => strtolower($email), 'tenant_id' => $tenantId]);
    }
}
