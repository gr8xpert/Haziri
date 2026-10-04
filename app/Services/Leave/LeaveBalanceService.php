<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeLeaveRule;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

/**
 * Leave balances (spec §5.6, §9 phase 2): yearly_upfront (prorated for joiners in that year) or monthly accrual,
 * carry-forward capped by carry_forward_max at year start. "Year" is the tenant's leave year (see LeaveYear);
 * the yearly quota comes from a grade / employment-type entitlement when one matches.
 */
final class LeaveBalanceService
{
    public function __construct(private readonly LeaveYear $leaveYear) {}

    public function balance(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        $balance = LeaveBalance::query()->firstOrNew([
            'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year,
        ]);
        if (! $balance->exists) {
            $balance->fill(['opening' => 0, 'used' => 0, 'pending' => 0, 'adjusted' => 0]);
            $balance->accrued = $this->accrued($employee, $type, $year);
            $balance->save();
        }

        return $balance;
    }

    /** Entitlement earned so far in leave year $year. */
    public function accrued(Employee $employee, LeaveType $type, int $year, ?CarbonImmutable $asOf = null): float
    {
        $quota = $this->entitlement($employee, $type);
        if ($quota <= 0 || ! $employee->leave_credit) {
            return 0.0;
        }
        // Month index inside the leave year (0 = first month) from which the employee earns leave.
        $firstMonth = $employee->joined_on ? max(0, $this->leaveYear->monthIndex($year, $employee->joined_on->format('Y-m-d'))) : 0;
        if ($firstMonth > 11) {
            return 0.0;
        }

        if ($type->accrual === 'monthly') {
            $asOf ??= CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'));
            $lastMonth = min(11, $this->leaveYear->monthIndex($year, $asOf->format('Y-m-d')));
            $months = max(0, $lastMonth - $firstMonth + 1);
        } else {
            $months = 12 - $firstMonth;
        }

        return self::roundHalf($quota * $months / 12);
    }

    /** Days per leave year for this employee (see rules()). */
    public function entitlement(Employee $employee, LeaveType $type): float
    {
        return $this->rules($employee, $type)['quota'];
    }

    /**
     * The leave type's numbers for one employee: their own special rule first, then the most specific grade /
     * employment-type row (grade + category → grade → category), then the leave type. A blank value falls through.
     *
     * @return array{quota: float, carry_forward_max: float, max_consecutive_days: ?int}
     */
    public function rules(Employee $employee, LeaveType $type): array
    {
        $match = LeaveEntitlement::query()->where('leave_type_id', $type->id)
            ->where(fn ($q) => $q->whereNull('grade_id')->orWhere('grade_id', $employee->grade_id))
            ->where(fn ($q) => $q->whereNull('employment_type')->orWhere('employment_type', $employee->employment_type?->value))
            ->get()
            ->sortByDesc(fn (LeaveEntitlement $e) => ($e->grade_id !== null ? 2 : 0) + ($e->employment_type !== null ? 1 : 0))
            ->first();

        $own = EmployeeLeaveRule::query()->where(['employee_id' => $employee->id, 'leave_type_id' => $type->id])->first();

        return [
            'quota' => $own?->days_per_year ?? ($match !== null ? $match->days_per_year : (float) $type->annual_quota_days),
            'carry_forward_max' => (float) ($own?->carry_forward_max ?? $match?->carry_forward_max ?? $type->carry_forward_max),
            'max_consecutive_days' => $own?->max_consecutive_days ?? $match?->max_consecutive_days
                ?? ($type->max_consecutive_days ? (int) $type->max_consecutive_days : null),
        ];
    }

    /** Monthly job: refresh accrued for every active type (idempotent; also applies entitlement changes). */
    public function accrueAll(int $year): int
    {
        $n = 0;
        foreach (LeaveType::query()->where('is_active', true)->get() as $type) {
            foreach (Employee::query()->where('status', '!=', EmployeeStatus::Left)->get() as $employee) {
                $balance = $this->balance($employee, $type, $year);
                $balance->update(['accrued' => $this->accrued($employee, $type, $year)]);
                $n++;
            }
        }

        return $n;
    }

    /** Year-start job: open $year balances, carrying forward up to carry_forward_max from $year − 1. */
    public function openYear(int $year): int
    {
        $n = 0;
        foreach (LeaveType::query()->where('is_active', true)->get() as $type) {
            foreach (Employee::query()->where('status', '!=', EmployeeStatus::Left)->get() as $employee) {
                $previous = LeaveBalance::query()->where(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year - 1])->first();
                $carry = $previous ? min($previous->remaining(), $this->rules($employee, $type)['carry_forward_max']) : 0.0;
                // An overdrawn balance follows the employee into the new year only where negatives are allowed.
                $carry = $type->allow_negative_balance ? $carry : max(0.0, $carry);
                $balance = $this->balance($employee, $type, $year);
                $balance->update(['opening' => $carry]);
                $n++;
            }
        }

        return $n;
    }

    private static function roundHalf(float $days): float
    {
        return round($days * 2) / 2;
    }
}
