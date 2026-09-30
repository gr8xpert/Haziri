<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\PayrollInputReport;

class PayrollInput extends ReportPage
{
    protected static string $report = PayrollInputReport::class;

    protected static ?string $slug = 'reports/payroll-input';

    protected static ?int $navigationSort = 120;
}
