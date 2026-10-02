<?php

declare(strict_types=1);

namespace App\Actions\Rosters;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Attendance\ShiftResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Legacy "Generate roster": put a group of employees on one shift for a whole pay-cycle month. Each employee gets one
 * assignment covering the month (it beats their standing shift; single-day roster cells still beat it). Running it
 * again for the same month replaces that month's assignment instead of stacking another one.
 */
final class GenerateMonthlyRoster
{
    public const SCOPES = ['all', 'branch', 'department', 'shift'];

    public function __construct(
        private readonly PayCycle $cycle,
        private readonly ShiftResolver $shifts,
    ) {}

    /**
     * @param  'all'|'branch'|'department'|'shift'  $scope  'shift' = employees currently on shift $scopeId
     * @return int employees rostered
     */
    public function __invoke(int $year, int $month, Shift $shift, string $scope, ?int $scopeId, User $by): int
    {
        if (! $by->can('shifts.manage')) {
            throw ValidationException::withMessages(['shift_id' => __('You cannot change rosters.')]);
        }
        if (! in_array($scope, self::SCOPES, true) || ($scope !== 'all' && $scopeId === null)) {
            throw ValidationException::withMessages(['scope' => __('Choose who the roster is for.')]);
        }
        [$from, $to] = $this->cycle->dates($year, $month);

        $employees = Employee::query()->where('status', '!=', EmployeeStatus::Left)
            ->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>=', $from))
            ->when($scope === 'branch', fn ($q) => $q->where('branch_id', $scopeId))
            ->when($scope === 'department', fn ($q) => $q->where('department_id', $scopeId))
            ->when($scope === 'shift', fn ($q) => $q->whereIn('id', $this->shifts->employeeIdsOnShift((int) $scopeId,
                min($from, CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d')))))
            ->pluck('id');

        DB::transaction(function () use ($employees, $shift, $from, $to) {
            foreach ($employees as $employeeId) {
                ShiftAssignment::query()->where('employee_id', $employeeId)
                    ->whereDate('effective_from', $from)->whereDate('effective_to', $to)->delete();
                ShiftAssignment::create(['employee_id' => $employeeId, 'shift_id' => $shift->id, 'effective_from' => $from, 'effective_to' => $to]);
            }
        });

        return $employees->count();
    }
}
