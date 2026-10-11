<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\MonthlyLeaveSummaryReport;
use App\Services\Settings\TenantSettings;

class MonthlyLeaveSummary extends ReportPage
{
    protected static string $report = MonthlyLeaveSummaryReport::class;

    protected static ?string $slug = 'reports/monthly-leave-summary';

    protected static ?int $navigationSort = 95;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('leave') && parent::canAccess();
    }
}
