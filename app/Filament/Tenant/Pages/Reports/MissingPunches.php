<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\MissingPunchReport;

class MissingPunches extends ReportPage
{
    protected static string $report = MissingPunchReport::class;

    protected static ?string $slug = 'reports/missing-punches';

    protected static ?int $navigationSort = 70;
}
