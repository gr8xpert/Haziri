<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LeaveTypes\Pages;

use App\Filament\Tenant\Resources\LeaveTypes\LeaveTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeaveTypes extends ManageRecords
{
    protected static string $resource = LeaveTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
