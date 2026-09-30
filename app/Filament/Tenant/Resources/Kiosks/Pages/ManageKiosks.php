<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Kiosks\Pages;

use App\Enums\DevicePunchSourceType;
use App\Filament\Tenant\Resources\Kiosks\KioskResource;
use App\Models\Device;
use App\Models\Kiosk;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageKiosks extends ManageRecords
{
    protected static string $resource = KioskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data) {
                // Every kiosk is also a device, so punches carry a device and it shows in the device list / sync report.
                $name = (string) \Illuminate\Support\Arr::pull($data, 'name', 'Reception tablet');
                $device = Device::create([
                    'name' => $name,
                    'serial_number' => 'KIOSK-' . Str::upper(Str::random(8)),
                    'type' => DevicePunchSourceType::Kiosk,
                    'branch_id' => $data['branch_id'] ?? null,
                ]);

                return Kiosk::create($data + ['device_id' => $device->id, 'token' => Kiosk::newToken()]);
            }),
        ];
    }
}
