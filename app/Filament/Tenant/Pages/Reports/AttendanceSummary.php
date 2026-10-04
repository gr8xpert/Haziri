<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\AttendanceSummaryReport;

class AttendanceSummary extends ReportPage
{
    protected static string $report = AttendanceSummaryReport::class;

    protected static ?string $slug = 'reports/attendance-summary';

    protected static ?int $navigationSort = 25;

    /** Merged into another report (Reports simplification): old links and bookmarks land on the replacement. */
    public function mount(): void
    {
        $this->redirect(MonthSummary::getUrl(['filters' => ['group_by' => 'employee']]));
    }
}
