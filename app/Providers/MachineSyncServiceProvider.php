<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\ResolveAdmsTenant;
use App\Listeners\IngestAdmsTransactions;
use App\Listeners\RecordDeviceUsers;
use App\Models\Device;
use App\Services\Devices\DeviceRegistrar;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use TanemRahman\ZktecoAdms\Events\TransactionsReceived;
use TanemRahman\ZktecoAdms\Events\UsersSynced;

/** ADMS push (tanemrahman/zkteco-adms), bridge-agent API and device registry wiring (spec §6.1–6.2). */
final class MachineSyncServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The package's /iclock routes use this alias; point it at our tenant-resolving wrapper.
        Route::aliasMiddleware('zkteco.adms.device', ResolveAdmsTenant::class);

        config([
            // Hazri owns the business rules: keep every punch (the device may have been offline for weeks),
            // don't auto-queue the package's own processing, and never trust a per-request default key.
            'zkteco-adms.attendance.retention_days' => 0,
            'zkteco-adms.attendance.queue_processing' => false,
            'zkteco-adms.options.server_name' => 'Hazri',
        ]);

        Event::listen(TransactionsReceived::class, IngestAdmsTransactions::class);
        Event::listen(UsersSynced::class, RecordDeviceUsers::class);

        Device::saving(fn (Device $d) => $d->isDirty('serial_number') ? app(DeviceRegistrar::class)->assertAvailable($d->serial_number) : null);
        Device::saved(fn (Device $d) => app(DeviceRegistrar::class)->sync($d));
        Device::deleted(fn (Device $d) => app(DeviceRegistrar::class)->forget($d));

        RateLimiter::for('agent', fn (Request $r) => Limit::perMinute(120)->by($r->bearerToken() ?: $r->ip()));
        RateLimiter::for('public-api', fn (Request $r) => Limit::perMinute(60)->by(tenant('id') . '|' . ($r->bearerToken() ?: $r->ip())));

        $this->loadRoutesFrom(base_path('routes/agent.php'));
    }
}
