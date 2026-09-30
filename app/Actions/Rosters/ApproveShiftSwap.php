<?php

declare(strict_types=1);

namespace App\Actions\Rosters;

use App\Models\ShiftAssignment;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Services\Attendance\ShiftResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Two employees swap shifts for one date (spec §9 phase 4): approval writes two one-day assignments. */
final class ApproveShiftSwap
{
    public function __construct(private readonly ShiftResolver $shifts) {}

    public function __invoke(ShiftSwapRequest $swap, User $by, bool $approve): ShiftSwapRequest
    {
        if ($swap->status !== 'pending') {
            throw ValidationException::withMessages(['swap' => __('This request was already decided.')]);
        }
        if (! $by->can('shifts.manage') && ! $by->can('leave.approve_first')) {
            throw ValidationException::withMessages(['swap' => __('You cannot approve shift swaps.')]);
        }

        return DB::transaction(function () use ($swap, $by, $approve) {
            if ($approve) {
                $date = $swap->date->format('Y-m-d');
                $tz = tenant('timezone') ?? config('hazri.default_timezone');
                $a = $this->shifts->resolve($swap->requester, $date, $tz)?->shiftId;
                $b = $this->shifts->resolve($swap->partner, $date, $tz)?->shiftId;
                if ($a === null || $b === null) {
                    throw ValidationException::withMessages(['swap' => __('Both employees need a shift on that date.')]);
                }
                foreach ([[$swap->requester_employee_id, $b], [$swap->partner_employee_id, $a]] as [$employeeId, $shiftId]) {
                    ShiftAssignment::query()->where('employee_id', $employeeId)->whereDate('effective_from', $date)->whereDate('effective_to', $date)->delete();
                    ShiftAssignment::create(['employee_id' => $employeeId, 'shift_id' => $shiftId, 'effective_from' => $date, 'effective_to' => $date]);
                }
            }
            $swap->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by->id, 'decided_at' => now()]);

            return $swap;
        });
    }
}
