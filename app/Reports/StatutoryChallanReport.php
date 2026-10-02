<?php

declare(strict_types=1);

namespace App\Reports;

/** R25 — EOBI and social security (PESSI / SESSI) contributions in a payroll run, for the monthly challans. */
final class StatutoryChallanReport extends PayrollRunReport
{
    public function title(): string
    {
        return __('EOBI & social security challan');
    }

    public function columns(array $filters): array
    {
        return ['code' => 'Code', 'name' => 'Name', 'cnic' => 'CNIC', 'eobi_no' => 'EOBI no.', 'eobi_base' => 'EOBI wage base',
            'eobi_ee' => 'EOBI employee', 'eobi_er' => 'EOBI company', 'eobi_total' => 'EOBI total', 'ss_no' => 'Social security no.',
            'ss_er' => 'Social security'];
    }

    public function rows(array $filters): array
    {
        $run = $this->run($filters);
        if ($run === null) {
            return [];
        }

        return $run->payslips()->with('employee')->get()->filter(fn ($s) => isset($s->meta['eobi_ee']) || isset($s->meta['ss_er']))
            ->sortBy('employee.code')->map(fn ($s) => [
                'code' => $s->employee->code, 'name' => $s->employee->fullName(), 'cnic' => $s->employee->cnic,
                'eobi_no' => $s->employee->eobi_no, 'eobi_base' => $s->meta['eobi_base'] ?? null,
                'eobi_ee' => $s->meta['eobi_ee'] ?? null, 'eobi_er' => $s->meta['eobi_er'] ?? null,
                'eobi_total' => isset($s->meta['eobi_ee']) ? $s->meta['eobi_ee'] + $s->meta['eobi_er'] : null,
                'ss_no' => $s->employee->social_security_no, 'ss_er' => $s->meta['ss_er'] ?? null,
            ])->values()->all();
    }

    public function summary(array $filters, array $rows): array
    {
        return ['EOBI total' => 'Rs ' . number_format(array_sum(array_column($rows, 'eobi_total'))),
            'Social security' => 'Rs ' . number_format(array_sum(array_column($rows, 'ss_er')))];
    }
}
