<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\AbsentRegisterReport;

class AbsentRegister extends ReportPage
{
    protected static string $report = AbsentRegisterReport::class;

    protected static ?string $slug = 'reports/absent-register';

    protected static ?int $navigationSort = 50;
}
