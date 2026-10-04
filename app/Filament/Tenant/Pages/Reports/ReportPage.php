<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages\Reports;

use App\Filament\Tenant\NavigationGroup;
use App\Reports\Report;
use App\Reports\ReportExporter;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Generic screen for a Report: filters form, on-screen table, Excel + PDF export.
 * Subclasses only set static::$report.
 */
abstract class ReportPage extends Page
{
    /** @var class-string<Report> */
    protected static string $report;

    protected string $view = 'filament.tenant.pages.report';

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Reports;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    /** @var array<string, mixed> */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('reports.view') || $user?->can('reports.view_team'));
    }

    public static function getNavigationLabel(): string
    {
        return static::report()->title();
    }

    public function getTitle(): string
    {
        return static::report()->title();
    }

    public static function report(): Report
    {
        return app(static::$report);
    }

    /** Filters can be preset from the link (?filters[month]=2026-09), e.g. Months (lock) → View. */
    public function mount(): void
    {
        $preset = array_filter((array) request()->query('filters', []), fn ($v) => is_scalar($v) && $v !== '');

        $this->form->fill(array_merge(static::report()->defaults(), $preset));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('filters')->columns(4)->components(static::report()->filterSchema())->live();
    }

    /** @return array{columns: array<string, string>, rows: list<array<string, mixed>>, summary: array<string, scalar>, subtitle: string} */
    public function getReportData(): array
    {
        $report = static::report();
        $filters = $this->filters ?? [];
        if (! $report->ready($filters)) {
            return ['columns' => [], 'rows' => [], 'summary' => [], 'subtitle' => __('Choose the filters above.')];
        }
        $rows = $report->rows($filters);

        return [
            'columns' => $report->columns($filters),
            'rows' => $rows,
            'summary' => $report->summary($filters, $rows),
            'subtitle' => $report->subtitle($filters),
        ];
    }

    protected function getHeaderActions(): array
    {
        $canExport = fn () => auth()->user()->can('reports.export');

        return [
            Action::make('xlsx')->label('Excel')->icon(Heroicon::OutlinedTableCells)->color('gray')->visible($canExport)
                ->action(fn (ReportExporter $x) => $x->xlsx(static::report(), $this->filters ?? [])),
            Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedDocumentChartBar)->color('gray')->visible($canExport)
                ->action(fn (ReportExporter $x) => $x->pdf(static::report(), $this->filters ?? [])),
        ];
    }
}
