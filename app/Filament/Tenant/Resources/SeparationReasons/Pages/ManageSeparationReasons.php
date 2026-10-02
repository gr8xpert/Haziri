<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\SeparationReasons\Pages;

use App\Filament\Tenant\Resources\SeparationReasons\SeparationReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSeparationReasons extends ManageRecords
{
    protected static string $resource = SeparationReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
