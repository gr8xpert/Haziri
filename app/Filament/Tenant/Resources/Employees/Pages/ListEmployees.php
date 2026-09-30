<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Employees\Pages;

use App\Filament\Tenant\Resources\Employees\EmployeeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label(__('Import from Excel'))->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->url(EmployeeResource::getUrl('import'))
                ->visible(fn () => auth()->user()->can('employees.import')),
            CreateAction::make(),
        ];
    }
}
