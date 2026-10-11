<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\PayrollRun;
use Filament\Forms\Components\Select;

/** R13 — Salary sheet: gross, each deduction, net (PDF + Excel). */
final class SalarySheetReport extends Report
{
    public function title(): string
    {
        return __('Salary sheet');
    }

    public function filterSchema(): array
    {
        return [Select::make('run_id')->label(__('Payroll run'))->required()
            ->options(fn () => PayrollRun::with('period')->latest('id')->get()->mapWithKeys(fn (PayrollRun $r) => [$r->id => $r->label() . " ({$r->status})"]))];
    }

    /** Starts on the newest run, like the other payroll-run reports. */
    public function defaults(): array
    {
        return ['run_id' => PayrollRun::query()->latest('id')->value('id')];
    }

    public function ready(array $filters): bool
    {
        return ! empty($filters['run_id']);
    }

    public function subtitle(array $filters): string
    {
        $run = PayrollRun::with('period')->find($filters['run_id'] ?? 0);

        return $run ? $run->label() . ($run->status === 'draft' ? ' — ' . __('DRAFT') : '') : '';
    }

    public function paper(): string
    {
        return 'landscape';
    }

    public function columns(array $filters): array
    {
        $cols = ['code' => 'Code', 'name' => 'Name', 'gross' => 'Gross'];
        foreach ($this->deductionLabels($filters) as $label) {
            $cols['d:' . md5($label)] = $label;
        }

        return $cols + ['deductions' => 'Total deductions', 'net' => 'Net pay'];
    }

    public function rows(array $filters): array
    {
        $run = PayrollRun::find($filters['run_id'] ?? 0);
        if ($run === null) {
            return [];
        }

        return $run->payslips()->with('employee')->get()->sortBy('employee.code')->map(function ($slip) {
            $row = ['code' => $slip->employee->code, 'name' => $slip->employee->fullName(), 'gross' => $slip->gross];
            foreach ($slip->lines as $line) {
                if ($line['type'] === 'deduction') {
                    $key = 'd:' . md5(self::group($line['label']));
                    $row[$key] = ($row[$key] ?? 0) + $line['amount'];
                }
            }

            return $row + ['deductions' => $slip->deductions, 'net' => $slip->net];
        })->values()->all();
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Gross' => number_format(array_sum(array_column($rows, 'gross'))), 'Net' => number_format(array_sum(array_column($rows, 'net')))];
    }

    /** @return list<string> */
    private function deductionLabels(array $filters): array
    {
        $run = PayrollRun::find($filters['run_id'] ?? 0);
        if ($run === null) {
            return [];
        }

        return $run->payslips->flatMap(fn ($s) => collect($s->lines)->where('type', 'deduction')->pluck('label'))
            ->map(fn ($l) => self::group($l))->unique()->values()->all();
    }

    /** "Attendance deduction (2 days × Rs 1,000)" → "Attendance deduction" so columns line up across employees. */
    private static function group(string $label): string
    {
        return trim(preg_replace('/\s*\(.*\)$/', '', $label) ?? $label);
    }
}
