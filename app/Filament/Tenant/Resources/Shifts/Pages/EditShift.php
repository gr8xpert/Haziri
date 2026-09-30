<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Shifts\Pages;

use App\Filament\Tenant\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShift extends EditRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        if ($this->record->is_default) {
            Shift::whereKeyNot($this->record->id)->update(['is_default' => false]);
        }
    }
}
