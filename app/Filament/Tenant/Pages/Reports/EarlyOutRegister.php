<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\EarlyOutRegisterReport;

class EarlyOutRegister extends ReportPage
{
    protected static string $report = EarlyOutRegisterReport::class;

    protected static ?string $slug = 'reports/early-out-register';

    protected static ?int $navigationSort = 40;
}
