<?php

declare(strict_types=1);

namespace App\Services\Attendance\Period;

use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use App\Services\Settings\TenantSettings;

/**
 * Builds an employee's PeriodRules from the tenant settings, including how much leave the
 * late / half-day deductions may be charged to when `late_adjustment_mode` = leave.
 */
final class PeriodRulesFactory
{
    private ?LeaveType $adjustmentType = null;

    private bool $typeLoaded = false;

    public function __construct(
        private readonly TenantSettings $settings,
        private readonly LeaveBalanceService $balances,
        private readonly LeaveYear $leaveYear,
    ) {}

    /** @param string $periodEnd Y-m-d, picks the leave year whose balance is charged */
    public function forEmployee(Employee $employee, string $periodEnd): PeriodRules
    {
        $isDailyWage = $employee->employment_type === EmploymentType::DailyWage;

        return PeriodRules::fromSettings(
            $this->settings->all(),
            (bool) $employee->late_deduction_exempt,
            $isDailyWage,
            $isDailyWage || $employee->late_deduction_exempt ? 0.0 : $this->leaveAvailable($employee, $periodEnd),
        );
    }

    /** The leave type charged in "leave adjustment" mode, or null in salary mode. */
    public function adjustmentLeaveType(): ?LeaveType
    {
        if (! $this->typeLoaded) {
            $this->typeLoaded = true;
            $code = $this->settings->get('late_adjustment_leave_type');
            $this->adjustmentType = $this->settings->get('late_adjustment_mode') === 'leave' && filled($code)
                ? LeaveType::query()->where('code', $code)->where('is_active', true)->first()
                : null;
        }

        return $this->adjustmentType;
    }

    private function leaveAvailable(Employee $employee, string $periodEnd): float
    {
        $type = $this->adjustmentLeaveType();
        if ($type === null) {
            return 0.0;
        }
        if ($type->allow_negative_balance) {
            return INF;
        }

        return max(0.0, $this->balances->balance($employee, $type, $this->leaveYear->yearFor($periodEnd))->remaining());
    }
}
