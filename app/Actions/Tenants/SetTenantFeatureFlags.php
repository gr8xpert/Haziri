<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Central\Tenant;
use App\Models\Central\TenantSetting;
use App\Services\Features\FeatureCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin override of a tenant's feature switches: central tenant_settings + mirrored tenant `settings` (spec §3.3).
 * Connected switches follow the catalog rules, so a client can never end up with a feature whose requirement is off.
 */
final class SetTenantFeatureFlags
{
    /**
     * @param  array<string, bool>  $flags  changed switches (others keep their current value)
     * @return array{flags: array<string, bool>, turned_on: list<string>, turned_off: list<string>}
     */
    public function __invoke(Tenant $tenant, array $flags): array
    {
        $result = FeatureCatalog::fromConfig()->apply(self::current($tenant), $flags);

        foreach ($result['flags'] as $flag => $enabled) {
            TenantSetting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => "feature.{$flag}"], ['value' => $enabled]);
        }

        $tenant->run(function () use ($result) {
            foreach ($result['flags'] as $flag => $enabled) {
                DB::table('settings')->updateOrInsert(
                    ['key' => "feature.{$flag}"],
                    ['value' => json_encode($enabled), 'updated_at' => now()],
                );
            }
        });

        return $result;
    }

    /** @return array<string, bool> every catalog feature, unset ones at their default */
    public static function current(Tenant $tenant): array
    {
        $catalog = FeatureCatalog::fromConfig();
        $stored = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'like', 'feature.%')->pluck('value', 'key');
        $flags = [];
        foreach ($catalog->keys() as $flag) {
            $flags[$flag] = (bool) ($stored["feature.{$flag}"] ?? $catalog->default($flag));
        }

        return $flags;
    }
}
