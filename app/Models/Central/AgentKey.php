<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Bearer key for the bridge agent. Only the SHA-256 hash is stored (spec §15 security). */
class AgentKey extends Model
{
    use CentralConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_used_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    /** @return array{key: AgentKey, plain: string} */
    public static function issue(string $tenantId, string $label): array
    {
        $plain = 'hz_' . Str::random(40);
        $key = static::create(['tenant_id' => $tenantId, 'label' => $label, 'key_hash' => hash('sha256', $plain)]);

        return ['key' => $key, 'plain' => $plain];
    }

    public static function findActive(string $plain): ?self
    {
        return static::query()->where('key_hash', hash('sha256', $plain))->whereNull('revoked_at')->first();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
