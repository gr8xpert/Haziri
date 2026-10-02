<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\StatutoryChallanReport;
use App\Services\Settings\TenantSettings;

class StatutoryChallan extends ReportPage
{
    protected static string $report = StatutoryChallanReport::class;

    protected static ?string $slug = 'reports/statutory-challan';

    protected static ?int $navigationSort = 165;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('payroll_social') && (bool) auth()->user()?->can('payroll.manage');
    }
}
