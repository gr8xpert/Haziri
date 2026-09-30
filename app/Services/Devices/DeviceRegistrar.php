<?php

declare(strict_types=1);

namespace App\Services\Devices;

use App\Enums\DevicePunchSourceType;
use App\Models\Central\DeviceRegistry;
use App\Models\Central\UnknownDevice;
use App\Models\Device;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the central serial → tenant registry in step with each tenant's `devices` table.
 * A serial can belong to only one tenant.
 */
final class DeviceRegistrar
{
    private const REGISTERED_TYPES = [DevicePunchSourceType::Adms, DevicePunchSourceType::Agent, DevicePunchSourceType::Kiosk];

    public function assertAvailable(string $serial, ?int $ignoreDeviceId = null): void
    {
        $owner = DeviceRegistry::query()->where('serial_number', $serial)->value('tenant_id');
        if ($owner !== null && $owner !== tenant('id')) {
            throw ValidationException::withMessages([
                'serial_number' => __('This serial number is registered to another company. Contact support.'),
            ]);
        }
    }

    public function sync(Device $device): void
    {
        if ($device->trashed() || ! in_array($device->type, self::REGISTERED_TYPES, true)) {
            $this->forget($device);

            return;
        }

        $this->assertAvailable($device->serial_number, $device->id);
        if ($device->wasChanged('serial_number') && $device->getOriginal('serial_number')) {
            DeviceRegistry::query()->where('serial_number', $device->getOriginal('serial_number'))
                ->where('tenant_id', tenant('id'))->delete();
        }

        DeviceRegistry::query()->updateOrCreate(
            ['serial_number' => $device->serial_number],
            ['tenant_id' => tenant('id'), 'type' => $device->type->value, 'comm_key' => $device->comm_key],
        );
        UnknownDevice::query()->where('serial_number', $device->serial_number)->delete();
    }

    public function forget(Device $device): void
    {
        DeviceRegistry::query()->where('serial_number', $device->serial_number)->where('tenant_id', tenant('id'))->delete();
    }
}
