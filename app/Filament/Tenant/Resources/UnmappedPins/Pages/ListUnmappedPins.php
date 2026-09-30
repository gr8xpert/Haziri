<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\UnmappedPins\Pages;

use App\Filament\Tenant\Resources\UnmappedPins\UnmappedPinResource;
use Filament\Resources\Pages\ListRecords;

class ListUnmappedPins extends ListRecords
{
    protected static string $resource = UnmappedPinResource::class;
}
