<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Shifts\Pages;

use App\Filament\Tenant\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use Filament\Resources\Pages\CreateRecord;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->is_default) {
            Shift::whereKeyNot($this->record->id)->update(['is_default' => false]);
        }
    }
}
