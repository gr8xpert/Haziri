<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\UnknownDevices\Pages;

use App\Filament\Central\Resources\UnknownDevices\UnknownDeviceResource;
use Filament\Resources\Pages\ListRecords;

class ListUnknownDevices extends ListRecords
{
    protected static string $resource = UnknownDeviceResource::class;
}
