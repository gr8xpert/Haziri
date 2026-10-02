<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Grades\Pages;

use App\Filament\Tenant\Resources\Grades\GradeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGrades extends ManageRecords
{
    protected static string $resource = GradeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
