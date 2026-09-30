<?php

declare(strict_types=1);

namespace App\Actions\Periods;

use App\Enums\EmploymentType;
use App\Enums\PeriodStatus;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRules;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Month lifecycle (spec §5.7): open → review → locked (snapshot, rows frozen) → unlock (owner/admin + reason, audited).
 */
final class PeriodActions
{
    public function __construct(
        private readonly PeriodCalculator $calculator,
        private readonly TenantSettings $settings,
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
        $end = CarbonImmutable::create($period->year, $period->month, 1)->endOfMonth();
        $this->assert($end->format('Y-m-d') < CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d'),
            __('A month can only be locked after it has ended.'));

        return DB::transaction(function () use ($period, $by) {
            $period->update([
                'status' => PeriodStatus::Locked, 'locked_by' => $by->id, 'locked_at' => now(),
                'summary' => $this->snapshot($period),
            ]);
            $this->rows($period)->update(['is_locked' => true, 'locked_by_period_id' => $period->id]);

            return $period;
        });
    }

    public function unlock(AttendancePeriod $period, User $by, string $reason): AttendancePeriod
    {
        $this->assert($by->can('periods.unlock'), __('Only the owner can unlock a month.'));
        $this->assert($period->status === PeriodStatus::Locked, __('This month is not locked.'));
        $this->assert(trim($reason) !== '', __('A reason is required to unlock.'));

        return DB::transaction(function () use ($period, $by, $reason) {
            $this->rows($period)->update(['is_locked' => false, 'locked_by_period_id' => null]);
            $period->update(['status' => PeriodStatus::Review, 'locked_by' => null, 'locked_at' => null]);
            activity()->causedBy($by)->performedOn($period)->event('unlocked')->withProperties(['reason' => $reason])
                ->log("Unlocked {$period->year}-{$period->month}: {$reason}");

            return $period;
        });
    }

    /** @return array<string, mixed> per-employee PeriodSummary + totals */
    public function snapshot(AttendancePeriod $period): array
    {
        $settings = $this->settings->all();
        $days = $this->rows($period)->get()->groupBy('employee_id');
        $employees = Employee::withTrashed()->whereIn('id', $days->keys())->get()->keyBy('id');

        $rows = [];
        foreach ($days as $employeeId => $list) {
            $e = $employees[$employeeId];
            $rows[$employeeId] = ['code' => $e->code, 'name' => $e->fullName()] + $this->calculator->summarise(
                $list->map(fn (AttendanceDay $d) => $d->toResult()),
                PeriodRules::fromSettings($settings, $e->late_deduction_exempt, $e->employment_type === EmploymentType::DailyWage),
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
                'overtime_minutes' => array_sum(array_column($rows, 'overtimeMinutes')),
            ],
        ];
    }

    private function rows(AttendancePeriod $period): \Illuminate\Database\Eloquent\Builder
    {
        $start = CarbonImmutable::create($period->year, $period->month, 1);

        return AttendanceDay::query()->whereBetween('date', [$start->format('Y-m-d'), $start->endOfMonth()->format('Y-m-d')]);
    }

    private function assert(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['period' => $message]);
        }
    }
}
