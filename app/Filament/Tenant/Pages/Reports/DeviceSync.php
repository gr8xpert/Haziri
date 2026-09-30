<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\DeviceSyncReport;

class DeviceSync extends ReportPage
{
    protected static string $report = DeviceSyncReport::class;

    protected static ?string $slug = 'reports/device-sync';

    protected static ?int $navigationSort = 140;
}
