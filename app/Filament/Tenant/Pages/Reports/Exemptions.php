<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\ExemptionReport;

class Exemptions extends ReportPage
{
    protected static string $report = ExemptionReport::class;

    protected static ?string $slug = 'reports/exemptions';

    protected static ?int $navigationSort = 105;
}
