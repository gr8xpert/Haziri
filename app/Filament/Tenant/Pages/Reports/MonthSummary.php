<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\MonthSummaryReport;

/** Opened from Months (lock) → View, and listed under Reports. */
class MonthSummary extends ReportPage
{
    protected static string $report = MonthSummaryReport::class;

    protected static ?string $slug = 'reports/month-summary';

    protected static ?int $navigationSort = 24;
}
