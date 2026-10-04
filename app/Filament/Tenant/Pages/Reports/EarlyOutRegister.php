<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Reports\EarlyOutRegisterReport;

class EarlyOutRegister extends ReportPage
{
    protected static string $report = EarlyOutRegisterReport::class;

    protected static ?string $slug = 'reports/early-out-register';

    protected static ?int $navigationSort = 40;

    /** Merged into another report (Reports simplification): old links and bookmarks land on the replacement. */
    public function mount(): void
    {
        $this->redirect(LateRegister::getUrl(['filters' => ['kind' => 'early']]));
    }
}
