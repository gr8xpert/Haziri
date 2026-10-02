<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\AttendanceSummaryReport;

class AttendanceSummary extends ReportPage
{
    protected static string $report = AttendanceSummaryReport::class;

    protected static ?string $slug = 'reports/attendance-summary';

    protected static ?int $navigationSort = 25;
}
