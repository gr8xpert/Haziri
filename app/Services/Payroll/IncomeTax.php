<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\TaxSlab;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/**
 * Salary income tax (Pakistan, salaried individuals): annual tax from the tenant's editable slab table plus the
 * high-income surcharge; monthly withholding spreads the projected year's tax still due over the months left.
 */
final class IncomeTax
{
    public function __construct(private readonly TenantSettings $settings) {}

    public function startMonth(): int
    {
        return max(1, min(12, (int) $this->settings->get('tax_year_start_month', 7)));
    }

    /** Year the tax year containing $date starts in (2025 = July 2025 – June 2026 with a July start). */
    public function taxYearFor(string $date): int
    {
        $d = CarbonImmutable::parse(substr($date, 0, 10));

        return $d->month >= $this->startMonth() ? $d->year : $d->year - 1;
    }

    /** Months left in the tax year counting the month of $date itself (June → 1 with a July start). */
    public function monthsLeft(string $date): int
    {
        $month = CarbonImmutable::parse(substr($date, 0, 10))->month;

        return 12 - (($month - $this->startMonth() + 12) % 12);
    }

    public function annualTax(int $income, int $taxYear): int
    {
        if ($income <= 0) {
            return 0;
        }
        $slabYear = TaxSlab::query()->where('tax_year', '<=', $taxYear)->max('tax_year') ?? TaxSlab::query()->min('tax_year');
        $slab = TaxSlab::query()->where('tax_year', $slabYear)->where('from_amount', '<', $income)
            ->orderByDesc('from_amount')->first();
        if ($slab === null) {
            return 0;
        }
        $tax = $slab->fixed_tax + ($income - $slab->from_amount) * $slab->rate_percent / 100;
        if ($income > (int) $this->settings->get('tax_surcharge_threshold', 10000000)) {
            $tax *= 1 + (float) $this->settings->get('tax_surcharge_percent', 0) / 100;
        }

        return (int) round($tax);
    }

    /**
     * @return array{tax: int, projected_income: int, annual_tax: int}
     */
    public function monthly(int $taxableThisMonth, int $ytdTaxable, int $ytdTax, string $periodEnd): array
    {
        $left = $this->monthsLeft($periodEnd);
        $projected = $ytdTaxable + $taxableThisMonth * $left;
        $annual = $this->annualTax($projected, $this->taxYearFor($periodEnd));

        return ['tax' => max(0, (int) round(($annual - $ytdTax) / $left)), 'projected_income' => $projected, 'annual_tax' => $annual];
    }
}
