<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\PayrollRun;
use Filament\Forms\Components\Select;

/** Base for reports over one payroll run (tax register, statutory challans). */
abstract class PayrollRunReport extends Report
{
    public function filterSchema(): array
    {
        return [Select::make('run_id')->label(__('Payroll run'))->required()
            ->options(fn () => PayrollRun::with('period')->latest('id')->get()->mapWithKeys(fn (PayrollRun $r) => [$r->id => $r->label() . " ({$r->status})"]))];
    }

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
        $run = $this->run($filters);

        return $run ? $run->label() . ($run->status === 'draft' ? ' — ' . __('DRAFT') : '') : '';
    }

    public function paper(): string
    {
        return 'landscape';
    }

    protected function run(array $filters): ?PayrollRun
    {
        return PayrollRun::with('period')->find($filters['run_id'] ?? 0);
    }
}
