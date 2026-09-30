<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ShiftSwaps\Pages;

use App\Filament\Tenant\Resources\ShiftSwaps\ShiftSwapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShiftSwaps extends ManageRecords
{
    protected static string $resource = ShiftSwapResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('New swap request'))];
    }
}
