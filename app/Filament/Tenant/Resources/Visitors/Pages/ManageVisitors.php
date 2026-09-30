<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Visitors\Pages;

use App\Filament\Tenant\Resources\Visitors\VisitorResource;
use App\Models\Visitor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVisitors extends ManageRecords
{
    protected static string $resource = VisitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Check in visitor'))
                ->mutateDataUsing(fn (array $data) => $data + [
                    'checked_in_at' => now(), 'badge_no' => Visitor::nextBadge(), 'entered_by' => auth()->id(),
                ]),
        ];
    }
}
