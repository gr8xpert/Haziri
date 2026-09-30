<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\MonthlySheetReport;

class MonthlySheet extends ReportPage
{
    protected static string $report = MonthlySheetReport::class;

    protected static ?string $slug = 'reports/monthly-sheet';

    protected static ?int $navigationSort = 20;
}
