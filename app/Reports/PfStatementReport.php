<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\PfLedgerEntry;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;

/** R26 — Provident fund balances: employee and company contributions to date, payouts deducted. */
final class PfStatementReport extends Report
{
    public function title(): string
    {
        return __('Provident fund statement');
    }

    public function filterSchema(): array
    {
        return [DatePicker::make('as_of')->label(__('As of'))->required(), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        return ['as_of' => CarbonImmutable::now($this->tz())->format('Y-m-d')];
    }

    public function subtitle(array $filters): string
    {
        return __('As of :d', ['d' => $filters['as_of'] ?? '']);
    }

    public function columns(array $filters): array
    {
        return ['code' => 'Code', 'name' => 'Name', 'employee' => 'Employee share', 'employer' => 'Company share', 'total' => 'Balance'];
    }

    public function rows(array $filters): array
    {
        $rows = [];
        foreach ($this->employees($filters)->where('pf_member', true)->get() as $e) {
            $rows[] = ['code' => $e->code, 'name' => $e->fullName()] + PfLedgerEntry::balance($e->id, $filters['as_of'] ?? null);
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Fund total' => 'Rs ' . number_format(array_sum(array_column($rows, 'total')))];
    }
}
