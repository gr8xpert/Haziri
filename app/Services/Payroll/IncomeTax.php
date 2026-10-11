<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\TaxSlab;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;

/**
 * Salary income tax (Pakistan, salaried individuals): annual tax from the tenant's editable slab table plus the
 * high-income surcharge (up to tax year 2025-26); monthly withholding spreads the projected year's tax still due over the months left.
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

    /**
     * The tax year whose slabs are used for $taxYear: its own, or the latest earlier one when this year's rates have
     * not been entered yet (payroll then warns HR, see hasSlabsFor()).
     */
    public function slabYear(int $taxYear): ?int
    {
        return TaxSlab::query()->where('tax_year', '<=', $taxYear)->max('tax_year') ?? TaxSlab::query()->min('tax_year');
    }

    public function hasSlabsFor(int $taxYear): bool
    {
        return TaxSlab::query()->where('tax_year', $taxYear)->exists();
    }

    public function annualTax(int $income, int $taxYear): int
    {
        if ($income <= 0) {
            return 0;
        }
        $slab = TaxSlab::query()->where('tax_year', $this->slabYear($taxYear))->where('from_amount', '<', $income)
            ->orderByDesc('from_amount')->first();
        if ($slab === null) {
            return 0;
        }
        $tax = $slab->fixed_tax + ($income - $slab->from_amount) * $slab->rate_percent / 100;
        // The high-income surcharge was abolished from tax year 2026-27 (Finance Act 2026).
        if ($taxYear <= (int) $this->settings->get('tax_surcharge_last_year', 2025)
            && $income > (int) $this->settings->get('tax_surcharge_threshold', 10000000)) {
            $tax *= 1 + (float) $this->settings->get('tax_surcharge_percent', 0) / 100;
        }

        return (int) round($tax);
    }

    /**
     * Regular pay is projected over the months left and its tax spread over them. A one-off amount (bonus, arrears)
     * is part of $taxableThisMonth but counted in the yearly income only once, and the extra tax it causes is
     * deducted in full this month.
     *
     * @return array{tax: int, projected_income: int, annual_tax: int}
     */
    public function monthly(int $taxableThisMonth, int $ytdTaxable, int $ytdTax, string $periodEnd, int $oneOffThisMonth = 0): array
    {
        $left = $this->monthsLeft($periodEnd);
        $taxYear = $this->taxYearFor($periodEnd);
        $oneOff = max(0, min($oneOffThisMonth, $taxableThisMonth));
        $regularProjected = $ytdTaxable + ($taxableThisMonth - $oneOff) * $left;
        $regularAnnual = $this->annualTax($regularProjected, $taxYear);
        $projected = $regularProjected + $oneOff;
        $annual = $oneOff > 0 ? $this->annualTax($projected, $taxYear) : $regularAnnual;
        $tax = (int) round(($regularAnnual - $ytdTax) / $left) + ($annual - $regularAnnual);

        return ['tax' => max(0, $tax), 'projected_income' => $projected, 'annual_tax' => $annual];
    }
}
