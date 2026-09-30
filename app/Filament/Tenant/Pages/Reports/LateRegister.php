<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\LateRegisterReport;

class LateRegister extends ReportPage
{
    protected static string $report = LateRegisterReport::class;

    protected static ?string $slug = 'reports/late-register';

    protected static ?int $navigationSort = 30;
}
