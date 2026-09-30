<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Branches\Pages;

use App\Filament\Tenant\Resources\Branches\BranchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBranches extends ManageRecords
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
