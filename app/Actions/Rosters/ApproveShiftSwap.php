<?php

declare(strict_types=1);

namespace App\Actions\Rosters;

use App\Enums\ShiftSwapStatus;
use App\Models\Employee;
use App\Models\Shift;
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
        if ($swap->status !== ShiftSwapStatus::Pending) {
            throw ValidationException::withMessages(['swap' => __('This request is not waiting for approval.')]);
        }
        if (! self::canDecide($by, $swap)) {
            throw ValidationException::withMessages(['swap' => __('You cannot approve shift swaps.')]);
        }

        return DB::transaction(function () use ($swap, $by, $approve) {
            if ($approve) {
                $date = $swap->date->format('Y-m-d');
                [$a, $b] = $this->shiftIds($swap->requester, $swap->partner, $date);
                foreach ([[$swap->requester_employee_id, $b], [$swap->partner_employee_id, $a]] as [$employeeId, $shiftId]) {
                    ShiftAssignment::query()->where('employee_id', $employeeId)->whereDate('effective_from', $date)->whereDate('effective_to', $date)->delete();
                    ShiftAssignment::create(['employee_id' => $employeeId, 'shift_id' => $shiftId, 'effective_from' => $date, 'effective_to' => $date]);
                }
            }
            $swap->update(['status' => $approve ? ShiftSwapStatus::Approved : ShiftSwapStatus::Rejected, 'decided_by' => $by->id, 'decided_at' => now()]);

            return $swap;
        });
    }

    /** HR (shifts.manage) decides any swap; a manager (leave.approve_first) only swaps asked by their direct reports. */
    public static function canDecide(User $user, ShiftSwapRequest $swap): bool
    {
        if ($user->can('shifts.manage')) {
            return true;
        }

        return $user->can('leave.approve_first') && $user->employee_id !== null
            && $swap->requester?->manager_employee_id === $user->employee_id;
    }

    /**
     * Each employee's shift on the date, refusing a swap that would change nothing.
     *
     * @return array{0: int, 1: int}
     */
    public function shiftIds(Employee $requester, Employee $partner, string $date): array
    {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $a = $this->shifts->resolve($requester, $date, $tz)?->shiftId;
        $b = $this->shifts->resolve($partner, $date, $tz)?->shiftId;
        if ($a === null || $b === null) {
            throw ValidationException::withMessages(['swap' => __('Both employees need a shift on that date.')]);
        }
        if ($a === $b) {
            throw ValidationException::withMessages(['swap' => __('Both are on the same shift that day, so there is nothing to swap.')]);
        }

        return [$a, $b];
    }

    /** "Ali Khan: Morning (06:00–14:00) ⇄ Bilal Malik: Night (22:00–06:00)" for the form, or null if not known yet. */
    public function describe(?Employee $requester, ?Employee $partner, ?string $date): ?string
    {
        if ($requester === null || $partner === null || blank($date)) {
            return null;
        }
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $name = fn (Employee $e) => ($id = $this->shifts->resolve($e, $date, $tz)?->shiftId) ? Shift::query()->find($id)?->displayName() : __('no shift');

        return "{$requester->fullName()}: {$name($requester)}  ⇄  {$partner->fullName()}: {$name($partner)}";
    }
}
