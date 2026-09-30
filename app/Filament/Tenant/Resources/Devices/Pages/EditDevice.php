<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Devices\Pages;

use App\Filament\Tenant\Resources\Devices\DeviceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
