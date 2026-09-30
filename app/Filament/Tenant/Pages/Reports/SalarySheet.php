<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\SalarySheetReport;
use App\Services\Settings\TenantSettings;

class SalarySheet extends ReportPage
{
    protected static string $report = SalarySheetReport::class;

    protected static ?string $slug = 'reports/salary-sheet';

    protected static ?int $navigationSort = 150;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('payroll_lite') && (bool) auth()->user()?->can('payroll.manage');
    }
}
