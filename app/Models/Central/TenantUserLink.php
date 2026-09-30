<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** One login email linked to several companies — lets an owner switch between them (spec §9 phase 4). */
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
