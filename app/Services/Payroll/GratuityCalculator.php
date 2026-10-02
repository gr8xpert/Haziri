<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/**
 * Gratuity: days' pay per year of service (default 30 days of the last basic per completed year; a part year of
 * at least `gratuity_part_year_months` counts as a year; nothing below `gratuity_min_years`).
 */
final class GratuityCalculator
{
    public function __construct(private readonly TenantSettings $settings) {}

    /** @return array{years: int, monthly_basis: int, amount: int} */
    public function accrued(Employee $employee, string $asOf): array
    {
        $none = ['years' => 0, 'monthly_basis' => 0, 'amount' => 0];
        if ($employee->joined_on === null) {
            return $none;
        }
        $joined = CarbonImmutable::parse($employee->joined_on->format('Y-m-d'));
        $end = CarbonImmutable::parse($asOf);
        if ($end->lessThan($joined)) {
            return $none;
        }
        $months = (int) $joined->diffInMonths($end->addDay());
        $years = intdiv($months, 12) + ($months % 12 >= (int) $this->settings->get('gratuity_part_year_months', 6) ? 1 : 0);
        if ($years < (int) $this->settings->get('gratuity_min_years', 1)) {
            return ['years' => $years] + $none;
        }
        $structure = SalaryStructure::effectiveFor($employee->id, $asOf);
        if ($structure === null) {
            return ['years' => $years] + $none;
        }
        $basis = $this->settings->get('gratuity_basis', 'basic') === 'gross'
            ? (int) $structure->basic + $structure->allowancesTotal()
            : (int) $structure->basic;
        $amount = (int) round($basis / 30 * (int) $this->settings->get('gratuity_days_per_year', 30) * $years);

        return ['years' => $years, 'monthly_basis' => $basis, 'amount' => $amount];
    }
}
