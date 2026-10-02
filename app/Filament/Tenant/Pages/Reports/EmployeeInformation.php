<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\EmployeeInformationReport;

class EmployeeInformation extends ReportPage
{
    protected static string $report = EmployeeInformationReport::class;

    protected static ?string $slug = 'reports/employee-information';

    protected static ?int $navigationSort = 5;
}
