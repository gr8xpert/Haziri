<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\OvertimeReport;

class Overtime extends ReportPage
{
    protected static string $report = OvertimeReport::class;

    protected static ?string $slug = 'reports/overtime';

    protected static ?int $navigationSort = 80;
}
