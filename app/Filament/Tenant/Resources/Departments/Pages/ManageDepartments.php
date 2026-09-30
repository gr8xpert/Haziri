<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Departments\Pages;

use App\Filament\Tenant\Resources\Departments\DepartmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDepartments extends ManageRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
