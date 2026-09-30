<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Devices\Pages;

use App\Filament\Tenant\Resources\Devices\DeviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;
}
