<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\LeaveBalanceReport;
use App\Services\Settings\TenantSettings;

class LeaveBalanceSheet extends ReportPage
{
    protected static string $report = LeaveBalanceReport::class;

    protected static ?string $slug = 'reports/leave-balances';

    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('leave') && parent::canAccess();
    }
}
