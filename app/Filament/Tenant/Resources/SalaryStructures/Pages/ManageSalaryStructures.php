<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\SalaryStructures\Pages;

use App\Filament\Tenant\Resources\SalaryStructures\SalaryStructureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSalaryStructures extends ManageRecords
{
    protected static string $resource = SalaryStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
