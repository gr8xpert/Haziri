<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Device;
use App\Models\Employee;
use App\Models\UnmappedPin;
use TanemRahman\ZktecoAdms\Events\UsersSynced;
use TanemRahman\ZktecoAdms\Models\ZktecoDeviceUser;

/**
 * USERINFO from a device: PINs enrolled on the machine but unknown to Haziri are listed as unmapped,
 * with the device-side name to make mapping easy (spec §6.1). Biometric templates are never stored.
 */
final class RecordDeviceUsers
{
    public function handle(UsersSynced $event): void
    {
        if (! tenancy()->initialized) {
            return;
        }
        $device = Device::query()->where('serial_number', $event->device->serial)->first();
        $known = Employee::query()->whereNotNull('device_pin')->pluck('device_pin')->flip();

        ZktecoDeviceUser::query()->where('serial', $event->device->serial)->each(function (ZktecoDeviceUser $u) use ($device, $known) {
            if (isset($known[$u->pin])) {
                return;
            }
            $row = UnmappedPin::query()->firstOrNew(['device_id' => $device?->id, 'device_pin' => (string) $u->pin]);
            $row->first_seen_at ??= now();
            $row->count ??= 0;
            $row->device_user_name = $u->name ?: $row->device_user_name;
            $row->save();
        });
    }
}
