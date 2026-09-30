<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\LeaveBalanceReport;

class LeaveBalanceSheet extends ReportPage
{
    protected static string $report = LeaveBalanceReport::class;

    protected static ?string $slug = 'reports/leave-balances';

    protected static ?int $navigationSort = 90;
}
