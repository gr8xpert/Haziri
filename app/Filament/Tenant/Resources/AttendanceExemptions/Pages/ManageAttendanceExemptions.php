<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\AttendanceExemptions\Pages;

use App\Filament\Tenant\Resources\AttendanceExemptions\AttendanceExemptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAttendanceExemptions extends ManageRecords
{
    protected static string $resource = AttendanceExemptionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data) => array_merge($data, ['created_by' => auth()->id()]))];
    }
}
