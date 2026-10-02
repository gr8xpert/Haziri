<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\GratuityLiabilityReport;
use App\Services\Settings\TenantSettings;

class GratuityLiability extends ReportPage
{
    protected static string $report = GratuityLiabilityReport::class;

    protected static ?string $slug = 'reports/gratuity-liability';

    protected static ?int $navigationSort = 175;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('payroll_gratuity') && (bool) auth()->user()?->can('payroll.manage');
    }
}
