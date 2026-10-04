<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Filament\Tenant\NavigationGroup;
use App\Reports\ReportCatalog;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** The one "Reports" menu entry: every report in five groups, each with what it shows and when to use it. */
class ReportsHub extends Page
{
    protected string $view = 'filament.tenant.pages.reports-hub';

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'reports';

    protected static ?string $title = 'Reports';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('reports.view') || $user?->can('reports.view_team'));
    }

    /** @return array<string, list<array{title: string, description: string, url: string}>> only the reports this user may open */
    public function getGroups(): array
    {
        $groups = [];
        foreach (ReportCatalog::groups() as $group => $reports) {
            foreach ($reports as $page => $description) {
                if ($page::canAccess()) {
                    $groups[$group][] = ['title' => (string) $page::getNavigationLabel(), 'description' => $description, 'url' => $page::getUrl()];
                }
            }
        }

        return $groups;
    }
}
