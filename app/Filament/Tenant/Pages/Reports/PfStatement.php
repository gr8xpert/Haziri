<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\PfStatementReport;
use App\Services\Settings\TenantSettings;

class PfStatement extends ReportPage
{
    protected static string $report = PfStatementReport::class;

    protected static ?string $slug = 'reports/pf-statement';

    protected static ?int $navigationSort = 170;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('payroll_pf') && (bool) auth()->user()?->can('payroll.manage');
    }
}
