<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Designations\Pages;

use App\Filament\Tenant\Resources\Designations\DesignationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDesignations extends ManageRecords
{
    protected static string $resource = DesignationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
