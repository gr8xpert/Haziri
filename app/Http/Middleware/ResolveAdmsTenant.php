<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Central\DeviceRegistry;
use App\Models\Central\Tenant;
use App\Models\Central\UnknownDevice;
use App\Models\Device;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use TanemRahman\ZktecoAdms\Http\Middleware\AdmsDevice;

/**
 * Runs in place of the package's `zkteco.adms.device` alias: SN → central device_registry → tenant,
 * initialise tenancy (so the package's tables and our punches live in the tenant DB), apply the device's
 * comm key / timezone, then hand over to the package's own device middleware.
 *
 * Unknown serials are recorded for the super-admin and refused (never "OK": the device would drop its buffer);
 * serials the super-admin blocked are refused before any lookup.
 */
final class ResolveAdmsTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $serial = trim((string) $request->query('SN', $request->query('sn', '')));
        if ($serial === '') {
            return app(AdmsDevice::class)->handle($request, $next); // package answers 400
        }

        if (UnknownDevice::isBlocked($serial)) {
            return response('Error: unknown device', 403)->header('Content-Type', 'text/plain');
        }

        $tenant = $this->tenantFor($serial);
        if ($tenant === null) {
            UnknownDevice::record($serial, $request->ip());

            return response('Error: unknown device', 403)->header('Content-Type', 'text/plain');
        }
        if ($tenant->status === TenantStatus::Suspended) {
            return response('Error: account suspended', 403)->header('Content-Type', 'text/plain');
        }

        tenancy()->initialize($tenant);

        $device = Device::query()->where('serial_number', $serial)->first();
        $tz = $device?->timezone ?? $tenant->timezone ?? config('hazri.default_timezone');
        config([
            'zkteco-adms.require_comm_key' => filled($device?->comm_key),
            'zkteco-adms.comm_key' => (string) $device?->comm_key,
            'zkteco-adms.attendance.device_timezone' => $tz,
            'zkteco-adms.options.timezone' => intdiv(CarbonImmutable::now($tz)->getOffset(), 3600),
            // Punch photos only when the tenant allows it (spec §6.1); stored under the tenant's storage.
            'zkteco-adms.attphoto.enabled' => (bool) app(TenantSettings::class)->get('store_punch_photos', true),
        ]);

        $device?->forceFill(['last_seen_at' => now(), 'ip' => $request->ip(), 'status' => 'online'])->saveQuietly();
        DeviceRegistry::query()->where('serial_number', $serial)->update(['last_seen_at' => now(), 'last_ip' => $request->ip()]);

        return app(AdmsDevice::class)->handle($request, $next);
    }

    private function tenantFor(string $serial): ?Tenant
    {
        if (config('hazri.mode') === 'dedicated') {
            return Tenant::query()->where('slug', config('hazri.dedicated_tenant'))->first();
        }
        $tenantId = DeviceRegistry::query()->where('serial_number', $serial)->value('tenant_id');

        return $tenantId ? Tenant::find($tenantId) : null;
    }
}
