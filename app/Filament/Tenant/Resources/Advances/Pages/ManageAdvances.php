<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Advances\Pages;

use App\Filament\Tenant\Resources\Advances\AdvanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAdvances extends ManageRecords
{
    protected static string $resource = AdvanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['remaining' => (int) $data['amount'], 'status' => 'active'])];
    }
}
