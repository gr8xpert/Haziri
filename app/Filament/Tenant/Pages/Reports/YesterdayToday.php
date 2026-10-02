<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\YesterdayTodayReport;

class YesterdayToday extends ReportPage
{
    protected static string $report = YesterdayTodayReport::class;

    protected static ?string $slug = 'reports/yesterday-today';

    protected static ?int $navigationSort = 15;
}
