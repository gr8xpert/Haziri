<?php

declare(strict_types=1);

namespace App\Actions\Punches;

use App\Enums\PunchStatus;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\UnmappedPin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HR maps a device PIN to an employee: the employee gets that PIN (if they have none), all unmapped punches with it
 * are re-attached and their days recomputed (spec §6.7). Returns the number of punches re-attached.
 */
final class MapUnmappedPinAction
{
    public function __invoke(UnmappedPin $unmapped, Employee $employee): int
    {
        $pin = $unmapped->device_pin;

        if ($employee->device_pin !== null && $employee->device_pin !== $pin) {
            throw ValidationException::withMessages([
                'employee' => "{$employee->fullName()} already uses PIN {$employee->device_pin}. Change it on the employee first.",
            ]);
        }
        $holder = Employee::query()->where('device_pin', $pin)->whereKeyNot($employee->id)->first();
        if ($holder !== null) {
            throw ValidationException::withMessages(['employee' => "PIN {$pin} already belongs to {$holder->fullName()}."]);
        }

        return DB::transaction(function () use ($unmapped, $employee, $pin) {
            if ($employee->device_pin === null) {
                $employee->update(['device_pin' => $pin]);
            }

            $punches = Punch::query()->where('status', PunchStatus::Unmapped)->where('device_pin', $pin)->get();
            foreach ($punches as $punch) {
                $punch->update(['employee_id' => $employee->id, 'status' => PunchStatus::Valid]);
            }

            UnmappedPin::query()->where('device_pin', $pin)->update(['resolved_employee_id' => $employee->id]);

            $dates = $punches->map(fn (Punch $p) => $p->local_date->format('Y-m-d'))->unique();
            foreach ($dates as $date) {
                IngestPunchAction::queueRecompute($employee->id, $date);
            }

            return $punches->count();
        });
    }
}
