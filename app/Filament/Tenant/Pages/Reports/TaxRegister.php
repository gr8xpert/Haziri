<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\TaxRegisterReport;
use App\Services\Settings\TenantSettings;

class TaxRegister extends ReportPage
{
    protected static string $report = TaxRegisterReport::class;

    protected static ?string $slug = 'reports/tax-register';

    protected static ?int $navigationSort = 160;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('payroll_tax') && (bool) auth()->user()?->can('payroll.manage');
    }
}
