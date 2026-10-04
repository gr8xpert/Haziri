<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Employees\Pages;

use App\Filament\Tenant\Resources\Employees\EmployeeResource;
use App\Models\Branch;
use App\Models\ShiftAssignment;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    /** With a single branch the field is hidden: put the employee in it, so its geofence and settings still apply. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['branch_id']) && Branch::query()->count() === 1) {
            $data['branch_id'] = Branch::query()->value('id');
        }

        return $data;
    }

    /** The "Shift" picked on the form (not an employee column) becomes a shift assignment from the joining date. */
    protected function afterCreate(): void
    {
        $shiftId = $this->form->getRawState()['shift_id'] ?? null;
        if (filled($shiftId)) {
            ShiftAssignment::create([
                'employee_id' => $this->record->id, 'shift_id' => (int) $shiftId, 'effective_from' => $this->record->joined_on,
            ]);
        }
    }
}
