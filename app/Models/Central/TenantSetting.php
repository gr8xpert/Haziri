<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Super-admin view of feature flags / overrides per tenant; mirrored into the tenant `settings` table (spec §3.3). */
class TenantSetting extends Model
{
    use CentralConnection;

    protected $fillable = ['tenant_id', 'key', 'value'];

    protected $casts = ['value' => 'json'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
