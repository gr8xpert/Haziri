<?php

declare(strict_types=1);

namespace App\Services\Attendance\Period;

/** Tenant + employee parameters for the monthly rollup (spec §7.4, §7.5). */
final readonly class PeriodRules
{
    public function __construct(
        /** 0 disables the "N lates = 1 absent" rollup. */
        public int $latesPerAbsent = 3,
        public float $halfDayCountsAs = 0.5,
        public float $absentDeductionDays = 1.0,
        public float $unpaidLeaveDeductionDays = 1.0,
        public bool $lateDeductionExempt = false,
        public bool $isDailyWage = false,
        public bool $dailyWagePaidHolidays = false,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function fromSettings(array $settings, bool $lateDeductionExempt, bool $isDailyWage): self
    {
        return new self(
            latesPerAbsent: (int) ($settings['lates_per_absent'] ?? 3),
            halfDayCountsAs: (float) ($settings['half_day_counts_as'] ?? 0.5),
            absentDeductionDays: (float) ($settings['absent_deduction_days'] ?? 1),
            unpaidLeaveDeductionDays: (float) ($settings['unpaid_leave_deduction_days'] ?? 1),
            lateDeductionExempt: $lateDeductionExempt,
            isDailyWage: $isDailyWage,
            dailyWagePaidHolidays: (bool) ($settings['daily_wage_paid_holidays'] ?? false),
        );
    }
}
