<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ShiftOverrides\Pages;

use App\Filament\Tenant\Resources\ShiftOverrides\ShiftOverrideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShiftOverrides extends ManageRecords
{
    protected static string $resource = ShiftOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
