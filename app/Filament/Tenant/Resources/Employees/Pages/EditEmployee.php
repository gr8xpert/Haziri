<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Employees\Pages;

use App\Filament\Tenant\Resources\Employees\EmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make()];
    }
}
