<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\MonthlyLeaveSummaryReport;

class MonthlyLeaveSummary extends ReportPage
{
    protected static string $report = MonthlyLeaveSummaryReport::class;

    protected static ?string $slug = 'reports/monthly-leave-summary';

    protected static ?int $navigationSort = 95;
}
