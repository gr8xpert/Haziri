<?php

declare(strict_types=1);

namespace App\Reports;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;

/** Base for reports filtered by from/to (+ branch/department). Defaults to the pay-cycle month to date. */
abstract class DateRangeReport extends Report
{
    public function filterSchema(): array
    {
        return [DatePicker::make('from')->required(), DatePicker::make('to')->required(), ...$this->orgFilters()];
    }

    public function defaults(): array
    {
        $now = CarbonImmutable::now($this->tz());

        return ['from' => $this->cycleRange($this->currentCycleMonth())[0]->format('Y-m-d'), 'to' => $now->format('Y-m-d')];
    }

    public function subtitle(array $filters): string
    {
        return ($filters['from'] ?? '') . ' → ' . ($filters['to'] ?? '');
    }

    public function ready(array $filters): bool
    {
        return ! empty($filters['from']) && ! empty($filters['to']);
    }
}
