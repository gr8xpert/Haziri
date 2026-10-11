<?php

declare(strict_types=1);

namespace App\Actions\Periods;

use App\Enums\PeriodStatus;
use App\Events\PeriodLocked;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRulesFactory;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Month lifecycle (spec §5.7): open → review → locked (snapshot, rows frozen) → unlock (owner/admin + reason, audited).
 * A "month" is the tenant's pay cycle (see PayCycle). In "leave adjustment" mode, locking charges each employee's
 * late / half-day days to leave; unlocking reverses those charges.
 */
final class PeriodActions
{
    public function __construct(
        private readonly PeriodCalculator $calculator,
        private readonly PeriodRulesFactory $rules,
        private readonly PayCycle $cycle,
        private readonly LeaveBalanceService $balances,
        private readonly LeaveYear $leaveYear,
    ) {}

    public function period(int $year, int $month): AttendancePeriod
    {
        return AttendancePeriod::query()->firstOrCreate(['year' => $year, 'month' => $month], ['status' => PeriodStatus::Open]);
    }

    public function review(AttendancePeriod $period): AttendancePeriod
    {
        $this->assert($period->status === PeriodStatus::Open, __('Only an open month can move to review.'));
        $period->update(['status' => PeriodStatus::Review]);

        return $period;
    }

    public function lock(AttendancePeriod $period, User $by): AttendancePeriod
    {
        $this->assert($by->can('periods.lock'), __('You cannot lock months.'));
        $this->assert($period->status !== PeriodStatus::Locked, __('This month is already locked.'));
        [, $end] = $this->cycle->dates($period->year, $period->month);
        $this->assert($end < CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d'),
            __('A month can only be locked after it has ended.'));

        return DB::transaction(function () use ($period, $by) {
            $summary = $this->snapshot($period);
            $period->update([
                'status' => PeriodStatus::Locked, 'locked_by' => $by->id, 'locked_at' => now(),
                'summary' => $summary,
            ]);
            $this->rows($period)->update(['is_locked' => true, 'locked_by_period_id' => $period->id]);
            $this->chargeLeaveAdjustments($period, $summary, $by);
            PeriodLocked::dispatch($period, $by);

            return $period;
        });
    }

    public function unlock(AttendancePeriod $period, User $by, string $reason): AttendancePeriod
    {
        $this->assert($by->can('periods.unlock'), __('Only the owner or an admin can unlock a month.'));
        $this->assert($period->status === PeriodStatus::Locked, __('This month is not locked.'));
        $this->assert(trim($reason) !== '', __('A reason is required to unlock.'));

        return DB::transaction(function () use ($period, $by, $reason) {
            $this->rows($period)->update(['is_locked' => false, 'locked_by_period_id' => null]);
            $this->reverseLeaveAdjustments($period);
            $period->update(['status' => PeriodStatus::Review, 'locked_by' => null, 'locked_at' => null]);
            activity()->causedBy($by)->performedOn($period)->event('unlocked')->withProperties(['reason' => $reason])
                ->log("Unlocked {$period->year}-{$period->month}: {$reason}");

            return $period;
        });
    }

    /** @return array<string, mixed> per-employee PeriodSummary + totals */
    public function snapshot(AttendancePeriod $period): array
    {
        [, $end] = $this->cycle->dates($period->year, $period->month);
        $days = $this->rows($period)->get()->groupBy('employee_id');
        $employees = Employee::withTrashed()->whereIn('id', $days->keys())->get()->keyBy('id');

        $rows = [];
        foreach ($days as $employeeId => $list) {
            $e = $employees[$employeeId];
            $rows[$employeeId] = ['code' => $e->code, 'name' => $e->fullName()] + $this->calculator->summarise(
                $list->map(fn (AttendanceDay $d) => $d->toResult()),
                $this->rules->forEmployee($e, $end),
            )->toArray();
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'employees' => $rows,
            'totals' => [
                'employees' => count($rows),
                'absent_days' => array_sum(array_column($rows, 'absentDays')),
                'late_count' => array_sum(array_column($rows, 'lateCount')),
                'deduction_days' => array_sum(array_column($rows, 'deductionDays')),
                'leave_adjusted_days' => array_sum(array_column($rows, 'leaveAdjustedDays')),
                'overtime_minutes' => array_sum(array_column($rows, 'overtimeMinutes')),
            ],
        ];
    }

    private function rows(AttendancePeriod $period): Builder
    {
        return AttendanceDay::query()->whereBetween('date', $this->cycle->dates($period->year, $period->month));
    }

    /** @param array<string, mixed> $summary snapshot from snapshot() */
    private function chargeLeaveAdjustments(AttendancePeriod $period, array $summary, User $by): void
    {
        $type = $this->rules->adjustmentLeaveType();
        if ($type === null) {
            return;
        }
        [, $end] = $this->cycle->dates($period->year, $period->month);
        $year = $this->leaveYear->yearFor($end);
        $label = $this->cycle->label($period->year, $period->month);
        foreach ($summary['employees'] ?? [] as $employeeId => $row) {
            $days = (float) ($row['leaveAdjustedDays'] ?? 0);
            $employee = $days > 0 ? Employee::withTrashed()->find($employeeId) : null;
            if ($employee === null) {
                continue;
            }
            LeaveAdjustment::create([
                'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year, 'days' => -$days,
                'effective_on' => $end, 'attendance_period_id' => $period->id, 'created_by' => $by->id,
                'reason' => __('Late / half-day adjustment — :month', ['month' => $label]),
            ]);
            $this->balances->balance($employee, $type, $year)->decrement('adjusted', $days);
        }
    }

    private function reverseLeaveAdjustments(AttendancePeriod $period): void
    {
        foreach (LeaveAdjustment::query()->where('attendance_period_id', $period->id)->get() as $adjustment) {
            LeaveBalance::query()->where([
                'employee_id' => $adjustment->employee_id, 'leave_type_id' => $adjustment->leave_type_id, 'year' => $adjustment->year,
            ])->decrement('adjusted', $adjustment->days);
            $adjustment->delete();
        }
    }

    private function assert(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['period' => $message]);
        }
    }
}
