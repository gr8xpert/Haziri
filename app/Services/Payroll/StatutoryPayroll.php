<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayItem;
use App\Models\Payslip;
use App\Models\SalaryStructure;
use App\Services\Settings\TenantSettings;

/**
 * Payroll Full on top of a Payroll Lite payslip, each part only when its feature is on:
 * one-off pay items, provident fund, EOBI, social security (PESSI/SESSI) and income tax (computed last,
 * on the taxable earnings net of attendance deductions). Employer contributions are listed but not deducted.
 */
final class StatutoryPayroll
{
    public function __construct(
        private readonly TenantSettings $settings,
        private readonly IncomeTax $tax,
    ) {}

    /**
     * @param  array{lines: list<array<string, mixed>>, gross: int, deductions: int, net: int, per_day: int}  $slip  from PayslipCalculator
     * @return array{lines: list<array<string, mixed>>, gross: int, deductions: int, net: int, per_day: int, meta: array<string, int>}
     */
    public function apply(Employee $employee, SalaryStructure $structure, array $slip, int $year, int $month, string $periodEnd, bool $isDailyWage): array
    {
        $on = fn (string $feature) => $this->settings->featureEnabled($feature);
        $lines = $slip['lines'];
        $meta = [];

        if ($on('payroll_adjustments')) {
            foreach (PayItem::query()->where(['employee_id' => $employee->id, 'year' => $year, 'month' => $month])->orderBy('id')->get() as $item) {
                $lines[] = ['label' => $item->label, 'type' => $item->type, 'amount' => $item->amount, 'code' => 'pay_item', 'taxable' => $item->taxable];
            }
        }

        if ($on('payroll_pf') && $employee->pf_member && ! $isDailyWage) {
            $ee = $this->percent((int) $structure->basic, 'pf_employee_percent');
            $er = $this->percent((int) $structure->basic, 'pf_employer_percent');
            $lines[] = ['label' => 'Provident fund', 'type' => 'deduction', 'amount' => $ee, 'code' => 'pf_ee'];
            $lines[] = ['label' => 'Provident fund (company share)', 'type' => 'employer', 'amount' => $er, 'code' => 'pf_er'];
            $meta += ['pf_ee' => $ee, 'pf_er' => $er];
        }

        if ($on('payroll_social')) {
            if ($employee->eobi_registered) {
                // Calculated EOBI replaces a manual "EOBI" fixed deduction on the salary structure.
                $lines = array_values(array_filter($lines, fn (array $l) => ! (($l['code'] ?? '') === 'fixed' && preg_match('/\beobi\b/i', $l['label']))));
                $base = (int) $this->settings->get('eobi_wage_base', 37000);
                $ee = $this->percent($base, 'eobi_employee_percent');
                $er = $this->percent($base, 'eobi_employer_percent');
                $lines[] = ['label' => 'EOBI', 'type' => 'deduction', 'amount' => $ee, 'code' => 'eobi_ee'];
                $lines[] = ['label' => 'EOBI (company share)', 'type' => 'employer', 'amount' => $er, 'code' => 'eobi_er'];
                $meta += ['eobi_ee' => $ee, 'eobi_er' => $er, 'eobi_base' => $base];
            }
            if ($employee->social_security_registered) {
                $base = (int) $this->settings->get('social_security_wage_base', 37000);
                $er = $this->percent($base, 'social_security_percent');
                $lines[] = ['label' => 'Social security (company share)', 'type' => 'employer', 'amount' => $er, 'code' => 'ss_er'];
                $meta += ['ss_er' => $er, 'ss_base' => $base];
            }
        }

        if ($on('payroll_tax') && ! $employee->tax_exempt) {
            $taxable = 0;
            $oneOff = 0;
            foreach ($lines as $l) {
                if ($l['type'] === 'earning' && ($l['taxable'] ?? true)) {
                    $taxable += $l['amount'];
                    $oneOff += ($l['code'] ?? '') === 'pay_item' ? $l['amount'] : 0;
                } elseif ($l['type'] === 'deduction' && in_array($l['code'] ?? '', ['attendance', 'not_employed'], true)) {
                    $taxable -= $l['amount']; // salary not paid is not income
                }
            }
            $taxable = max(0, $taxable);
            [$ytdTaxable, $ytdTax] = $this->yearToDate($employee, $periodEnd);
            $tax = $this->tax->monthly($taxable, $ytdTaxable, $ytdTax, $periodEnd, $oneOff);
            if ($tax['tax'] > 0) {
                $lines[] = ['label' => 'Income tax', 'type' => 'deduction', 'amount' => $tax['tax'], 'code' => 'tax'];
            }
            $meta += ['taxable' => $taxable, 'tax' => $tax['tax'], 'ytd_taxable' => $ytdTaxable + $taxable, 'ytd_tax' => $ytdTax + $tax['tax'],
                'projected_income' => $tax['projected_income'], 'annual_tax' => $tax['annual_tax']];
        }

        $sum = fn (string $type) => (int) array_sum(array_map(fn ($l) => $l['type'] === $type ? $l['amount'] : 0, $lines));
        $gross = $sum('earning');
        $deductions = $sum('deduction');

        return ['lines' => $lines, 'gross' => $gross, 'deductions' => $deductions, 'net' => $gross - $deductions,
            'per_day' => $slip['per_day'], 'meta' => $meta];
    }

    /**
     * Taxable income and tax on this employee's earlier payslips in the same tax year.
     *
     * @return array{0: int, 1: int}
     */
    private function yearToDate(Employee $employee, string $periodEnd): array
    {
        $year = $this->tax->taxYearFor($periodEnd);
        $taxable = 0;
        $tax = 0;
        foreach (Payslip::query()->with('run.period')->where('employee_id', $employee->id)->get() as $slip) {
            $end = $slip->run?->period?->dates()[1];
            if ($end !== null && $end < $periodEnd && $this->tax->taxYearFor($end) === $year) {
                $taxable += (int) ($slip->meta['taxable'] ?? 0);
                $tax += (int) ($slip->meta['tax'] ?? 0);
            }
        }

        return [$taxable, $tax];
    }

    private function percent(int $base, string $setting): int
    {
        return (int) round($base * (float) $this->settings->get($setting, 0) / 100);
    }
}
