<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\KioskDevices\Pages;

use App\Filament\Tenant\Resources\KioskDevices\KioskDeviceResource;
use Filament\Resources\Pages\ListRecords;

class ListKioskDevices extends ListRecords
{
    protected static string $resource = KioskDeviceResource::class;

    public function getSubheading(): ?string
    {
        return __('A locked kiosk works only on tablets approved here. When a new tablet opens the kiosk link it shows a code; approve the row with the same code.');
    }
}
