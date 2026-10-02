<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\BranchSummaryReport;
use App\Services\Settings\TenantSettings;

class BranchSummary extends ReportPage
{
    protected static string $report = BranchSummaryReport::class;

    protected static ?string $slug = 'reports/branch-summary';

    protected static ?int $navigationSort = 27;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('multi_branch') && parent::canAccess();
    }
}
