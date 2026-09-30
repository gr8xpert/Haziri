<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Central\Tenant;
use App\Models\Central\TenantSetting;
use Illuminate\Support\Facades\DB;

/** Super-admin override of a tenant's feature flags: central tenant_settings + mirrored tenant `settings` (spec §3.3). */
final class SetTenantFeatureFlags
{
    /**
     * @param  array<string, bool>  $flags
     */
    public function __invoke(Tenant $tenant, array $flags): void
    {
        $known = array_flip(config('hazri.feature_flags'));
        $flags = array_intersect_key($flags, $known);

        foreach ($flags as $flag => $enabled) {
            TenantSetting::updateOrCreate(['tenant_id' => $tenant->id, 'key' => "feature.{$flag}"], ['value' => (bool) $enabled]);
        }

        $tenant->run(function () use ($flags) {
            foreach ($flags as $flag => $enabled) {
                DB::table('settings')->updateOrInsert(
                    ['key' => "feature.{$flag}"],
                    ['value' => json_encode((bool) $enabled), 'updated_at' => now()],
                );
            }
        });
    }

    /** @return array<string, bool> */
    public static function current(Tenant $tenant): array
    {
        $stored = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'like', 'feature.%')->pluck('value', 'key');
        $flags = [];
        foreach (config('hazri.feature_flags') as $flag) {
            $flags[$flag] = (bool) ($stored["feature.{$flag}"] ?? false);
        }

        return $flags;
    }
}
