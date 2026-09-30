<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\PunctualityScorecardReport;

class PunctualityScorecard extends ReportPage
{
    protected static string $report = PunctualityScorecardReport::class;

    protected static ?string $slug = 'reports/punctuality';

    protected static ?int $navigationSort = 170;
}
