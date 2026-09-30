<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Filament\Tenant\Pages\Reports\DepartmentSummary;
use App\Filament\Tenant\Pages\Reports\DeviceSync;
use App\Filament\Tenant\Pages\Reports\PunctualityScorecard;
use App\Filament\Tenant\Widgets\Analytics\AttendanceTrendChart;
use App\Filament\Tenant\Widgets\Analytics\HeadlineStats;
use App\Filament\Tenant\Widgets\Analytics\TopLateChart;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Analytics (spec §9 phase 4). Detailed tables live in the linked reports (department comparison, device reliability, scorecard). */
class Analytics extends Page
{
    protected string $view = 'filament.tenant.pages.analytics';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('reports.view');
    }

    protected function getHeaderWidgets(): array
    {
        return [HeadlineStats::class, AttendanceTrendChart::class, TopLateChart::class];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scorecard')->label(__('Punctuality scorecard'))->color('gray')->url(PunctualityScorecard::getUrl()),
            Action::make('departments')->label(__('Department comparison'))->color('gray')->url(DepartmentSummary::getUrl()),
            Action::make('devices')->label(__('Device reliability'))->color('gray')->url(DeviceSync::getUrl()),
        ];
    }
}
