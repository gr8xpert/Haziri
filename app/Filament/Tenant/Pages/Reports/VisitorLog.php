<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\VisitorLogReport;
use App\Services\Settings\TenantSettings;

/** Needs the visitor log in the plan and full report access; managers' team-only access does not cover visitors. */
class VisitorLog extends ReportPage
{
    protected static string $report = VisitorLogReport::class;

    protected static ?string $slug = 'reports/visitor-log';

    protected static ?int $navigationSort = 160;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('visitors') && (bool) auth()->user()?->can('reports.view');
    }
}
