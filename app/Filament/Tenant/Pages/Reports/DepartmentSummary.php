<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\DepartmentSummaryReport;

class DepartmentSummary extends ReportPage
{
    protected static string $report = DepartmentSummaryReport::class;

    protected static ?string $slug = 'reports/department-summary';

    protected static ?int $navigationSort = 110;

    /** Merged into another report (Reports simplification): old links and bookmarks land on the replacement. */
    public function mount(): void
    {
        $this->redirect(MonthSummary::getUrl(['filters' => ['group_by' => 'department']]));
    }
}
