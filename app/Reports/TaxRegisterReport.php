<?php

declare(strict_types=1);

namespace App\Reports;

/** R24 — Income tax deducted from salaries in a payroll run (for the monthly withholding statement). */
final class TaxRegisterReport extends PayrollRunReport
{
    public function title(): string
    {
        return __('Income tax register');
    }

    public function columns(array $filters): array
    {
        return ['code' => 'Code', 'name' => 'Name', 'cnic' => 'CNIC', 'ntn' => 'NTN', 'taxable' => 'Taxable income', 'tax' => 'Tax deducted',
            'ytd_taxable' => 'Taxable (year to date)', 'ytd_tax' => 'Tax (year to date)', 'annual' => 'Projected annual tax'];
    }

    public function rows(array $filters): array
    {
        $run = $this->run($filters);
        if ($run === null) {
            return [];
        }

        return $run->payslips()->with('employee')->get()->filter(fn ($s) => isset($s->meta['taxable']))->sortBy('employee.code')
            ->map(fn ($s) => [
                'code' => $s->employee->code, 'name' => $s->employee->fullName(), 'cnic' => $s->employee->cnic, 'ntn' => $s->employee->ntn,
                'taxable' => $s->meta['taxable'], 'tax' => $s->meta['tax'], 'ytd_taxable' => $s->meta['ytd_taxable'] ?? null,
                'ytd_tax' => $s->meta['ytd_tax'] ?? null, 'annual' => $s->meta['annual_tax'] ?? null,
            ])->values()->all();
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Employees' => count($rows), 'Tax deducted' => 'Rs ' . number_format(array_sum(array_column($rows, 'tax')))];
    }
}
