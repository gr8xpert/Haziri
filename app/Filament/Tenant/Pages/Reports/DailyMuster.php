<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\DailyMusterReport;

class DailyMuster extends ReportPage
{
    protected static string $report = DailyMusterReport::class;

    protected static ?string $slug = 'reports/daily-muster';

    protected static ?int $navigationSort = 10;
}
