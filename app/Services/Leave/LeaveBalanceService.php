<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;

/**
 * Leave balances (spec §5.6, §9 phase 2): yearly_upfront (prorated for joiners in that year) or monthly accrual,
 * carry-forward capped by carry_forward_max at year start.
 */
final class LeaveBalanceService
{
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

    /** Entitlement earned so far in $year. */
    public function accrued(Employee $employee, LeaveType $type, int $year, ?CarbonImmutable $asOf = null): float
    {
        $quota = (float) $type->annual_quota_days;
        if ($quota <= 0) {
            return 0.0;
        }
        $joined = $employee->joined_on ? CarbonImmutable::parse($employee->joined_on->format('Y-m-d')) : null;
        $firstMonth = ($joined && $joined->year === $year) ? $joined->month : ($joined && $joined->year > $year ? 13 : 1);

        if ($type->accrual === 'monthly') {
            $asOf ??= CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'));
            $lastMonth = $asOf->year > $year ? 12 : ($asOf->year < $year ? 0 : $asOf->month);
            $months = max(0, $lastMonth - $firstMonth + 1);
        } else {
            $months = max(0, 12 - $firstMonth + 1);
        }

        return self::roundHalf($quota * $months / 12);
    }

    /** Monthly job: refresh accrued for monthly-accrual types (idempotent). */
    public function accrueAll(int $year): int
    {
        $n = 0;
        foreach (LeaveType::query()->where('is_active', true)->where('accrual', 'monthly')->get() as $type) {
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
                $carry = $previous ? min(max(0.0, $previous->remaining()), (float) $type->carry_forward_max) : 0.0;
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
