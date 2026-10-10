<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\EmployeeStatus;
use App\Services\Payroll\GratuityCalculator;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;

/** R27 — Gratuity owed if every employee left on the chosen date (for provisioning). */
final class GratuityLiabilityReport extends Report
{
    public function title(): string
    {
        return __('Gratuity liability');
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
        return __('As of :d', ['d' => self::day($filters['as_of'] ?? null)]);
    }

    public function columns(array $filters): array
    {
        return ['code' => 'Code', 'name' => 'Name', 'joined' => 'Joined', 'years' => 'Years counted', 'basis' => 'Monthly basis', 'amount' => 'Gratuity'];
    }

    public function rows(array $filters): array
    {
        $calc = app(GratuityCalculator::class);
        $rows = [];
        foreach ($this->employees($filters)->where('status', '!=', EmployeeStatus::Left)->get() as $e) {
            $g = $calc->accrued($e, $filters['as_of']);
            $rows[] = ['code' => $e->code, 'name' => $e->fullName(), 'joined' => self::day($e->joined_on),
                'years' => $g['years'], 'basis' => $g['monthly_basis'] ?: null, 'amount' => $g['amount']];
        }

        return $rows;
    }

    public function summary(array $filters, array $rows): array
    {
        return ['Total liability' => 'Rs ' . number_format(array_sum(array_column($rows, 'amount')))];
    }
}
