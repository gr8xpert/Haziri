<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\LeaveRegisterReport;
use App\Services\Settings\TenantSettings;

class LeaveRegister extends ReportPage
{
    protected static string $report = LeaveRegisterReport::class;

    protected static ?string $slug = 'reports/leave-register';

    protected static ?int $navigationSort = 100;

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('leave') && parent::canAccess();
    }
}
