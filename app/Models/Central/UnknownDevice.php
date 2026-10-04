<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A device pushed to adms.* with a serial no tenant owns yet; super-admin attaches it (spec §6.1) or blocks it.
 * A stray machine checks in every ~30 s, so it is written at most once a minute, and a blocked one is turned away
 * from the cache without touching the database.
 */
class UnknownDevice extends Model
{
    use CentralConnection, Prunable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_seen_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime', 'blocked_at' => 'immutable_datetime'];
    }

    public static function record(string $serial, ?string $ip): void
    {
        if (! Cache::add(self::seenKey($serial), true, 60)) {
            return;
        }
        $row = static::firstOrNew(['serial_number' => $serial]);
        if ($row->blocked_at !== null) {
            Cache::forever(self::blockedKey($serial), true); // the cache was cleared since it was blocked

            return;
        }
        $row->first_seen_at ??= now();
        $row->last_seen_at = now();
        $row->ip = $ip;
        $row->hits = ($row->hits ?? 0) + 1;
        $row->save();
    }

    public static function isBlocked(string $serial): bool
    {
        return Cache::has(self::blockedKey($serial));
    }

    public function block(): void
    {
        $this->update(['blocked_at' => now()]);
        Cache::forever(self::blockedKey($this->serial_number), true);
    }

    public function unblock(): void
    {
        $this->update(['blocked_at' => null]);
        self::forgetSerial($this->serial_number);
    }

    /**
     * Companies that probably own this machine: their registered machines last connected from the same IP address
     * (machines in one office share its internet connection).
     *
     * @return Collection<int, Tenant>
     */
    public function likelyTenants(): Collection
    {
        if (blank($this->ip)) {
            return new Collection;
        }
        $ids = DeviceRegistry::query()->where('last_ip', $this->ip)->distinct()->pluck('tenant_id');

        return Tenant::query()->whereIn('id', $ids)->orderBy('company_name')->get();
    }

    /** The serial was attached to a company or its row deleted: let it through again. */
    public static function forgetSerial(string $serial): void
    {
        Cache::forget(self::blockedKey($serial));
        Cache::forget(self::seenKey($serial));
    }

    /** Unblocked machines not seen for 30 days (model:prune). */
    public function prunable(): Builder
    {
        return static::query()->whereNull('blocked_at')->where('last_seen_at', '<', now()->subDays(30));
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $device) => self::forgetSerial($device->serial_number));
    }

    private static function blockedKey(string $serial): string
    {
        return "adms:blocked:{$serial}";
    }

    private static function seenKey(string $serial): string
    {
        return "adms:unknown-seen:{$serial}";
    }
}
