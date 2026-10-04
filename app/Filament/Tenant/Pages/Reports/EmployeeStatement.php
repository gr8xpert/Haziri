<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\EmployeeStatementReport;

class EmployeeStatement extends ReportPage
{
    protected static string $report = EmployeeStatementReport::class;

    protected static ?string $slug = 'reports/employee-statement';

    /** Superseded on the menu by the Monthly attendance report; kept for links and the Excel export. */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 60;

    /** Merged into another report (Reports simplification): old links and bookmarks land on the replacement. */
    public function mount(): void
    {
        $filters = (array) request()->query('filters', []);
        $this->redirect(MonthlyAttendance::getUrl(array_filter(['month' => $filters['month'] ?? null, 'employee' => $filters['employee_id'] ?? null], 'is_scalar')));
    }
}
