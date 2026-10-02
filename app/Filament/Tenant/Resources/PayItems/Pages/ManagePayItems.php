<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PayItems\Pages;

use App\Filament\Tenant\Resources\PayItems\PayItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePayItems extends ManageRecords
{
    protected static string $resource = PayItemResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data) => PayItemResource::splitMonth($data) + ['created_by' => auth()->id()])];
    }
}
