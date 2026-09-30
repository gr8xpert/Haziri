<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Plans\Pages;

use App\Filament\Central\Resources\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePlans extends ManageRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
