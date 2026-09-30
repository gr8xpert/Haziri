<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Serial number → tenant, for ADMS pushes and agent uploads that arrive on central domains (spec §4.1). */
class DeviceRegistry extends Model
{
    use CentralConnection;

    protected $table = 'device_registry';

    protected $guarded = ['id'];

    protected $hidden = ['comm_key'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
