<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** A device pushed to adms.* with a serial no tenant owns yet; super-admin attaches it (spec §6.1). */
class UnknownDevice extends Model
{
    use CentralConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime'];
    }

    public static function record(string $serial, ?string $ip): void
    {
        $row = static::firstOrNew(['serial_number' => $serial]);
        $row->first_seen_at ??= now();
        $row->last_seen_at = now();
        $row->ip = $ip;
        $row->hits = ($row->hits ?? 0) + 1;
        $row->save();
    }
}
