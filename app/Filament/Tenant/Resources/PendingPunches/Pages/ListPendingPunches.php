<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PendingPunches\Pages;

use App\Filament\Tenant\Resources\PendingPunches\PendingPunchResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingPunches extends ListRecords
{
    protected static string $resource = PendingPunchResource::class;
}
