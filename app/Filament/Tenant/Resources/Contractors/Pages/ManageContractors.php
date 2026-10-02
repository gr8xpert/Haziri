<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Contractors\Pages;

use App\Filament\Tenant\Resources\Contractors\ContractorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContractors extends ManageRecords
{
    protected static string $resource = ContractorResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
