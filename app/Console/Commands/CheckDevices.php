<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DevicePunchSourceType;
use App\Events\DeviceWentOffline;
use App\Models\Device;
use App\Services\Devices\DeviceCommander;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Device housekeeping for the current tenant (run via tenants:run):
 *  - mark ADMS/agent devices offline after 15 min silence, firing DeviceWentOffline once per outage;
 *  - --sync-time: queue a time sync for every online ADMS/agent device (scheduled daily, spec §6.1).
 */
#[Signature('hazri:devices:check {--sync-time}')]
#[Description('Mark silent devices offline; optionally queue a time sync')]
final class CheckDevices extends Command
{
    public const OFFLINE_AFTER_MINUTES = 15;

    public function handle(DeviceCommander $commander): int
    {
        $networked = [DevicePunchSourceType::Adms, DevicePunchSourceType::Agent];

        $silent = Device::query()->whereIn('type', $networked)->where('status', '!=', 'offline')
            ->whereNotNull('last_seen_at')->where('last_seen_at', '<', now()->subMinutes(self::OFFLINE_AFTER_MINUTES))->get();
        foreach ($silent as $device) {
            $device->forceFill(['status' => 'offline'])->saveQuietly();
            DeviceWentOffline::dispatch($device);
        }

        $synced = 0;
        if ($this->option('sync-time')) {
            foreach (Device::query()->whereIn('type', $networked)->where('status', 'online')->get() as $device) {
                $synced += $commander->syncTime($device);
            }
        }

        $this->info("{$silent->count()} device(s) went offline; {$synced} time sync(s) queued.");

        return self::SUCCESS;
    }
}
