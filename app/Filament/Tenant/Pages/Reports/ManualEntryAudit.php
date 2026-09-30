<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\ManualEntryAuditReport;

class ManualEntryAudit extends ReportPage
{
    protected static string $report = ManualEntryAuditReport::class;

    protected static ?string $slug = 'reports/manual-entry-audit';

    protected static ?int $navigationSort = 130;
}
