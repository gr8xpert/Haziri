<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PayrollRuns;

use App\Actions\Payroll\PayrollActions;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\PayrollRun;
use App\Reports\ReportExporter;
use App\Reports\SalarySheetReport;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PayrollRunResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = PayrollRun::class;

    protected static string $feature = 'payroll_lite';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Payroll;

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Payroll runs';

    protected static string $viewPermission = 'payroll.manage';

    protected static string $managePermission = 'payroll.manage';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('period_id')->label('Month')->formatStateUsing(fn (PayrollRun $r) => $r->label()),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'final' ? 'success' : 'warning'),
                TextColumn::make('totals.employees')->label('Employees'),
                TextColumn::make('totals.gross')->label('Gross')->prefix('Rs ')->numeric(),
                TextColumn::make('totals.deductions')->label('Deductions')->prefix('Rs ')->numeric(),
                TextColumn::make('totals.net')->label('Net')->prefix('Rs ')->numeric(),
                TextColumn::make('totals.skipped_no_structure')->label('No salary set')->color('warning'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('sheet')->label('Salary sheet (Excel)')->icon(Heroicon::OutlinedTableCells)
                        ->action(fn (PayrollRun $r, ReportExporter $x) => $x->xlsx(new SalarySheetReport, ['run_id' => $r->id])),
                    Action::make('sheetPdf')->label('Salary sheet (PDF)')->icon(Heroicon::OutlinedDocumentChartBar)
                        ->action(fn (PayrollRun $r, ReportExporter $x) => $x->pdf(new SalarySheetReport, ['run_id' => $r->id])),
                    Action::make('bank')->label('Bank transfer CSV')->icon(Heroicon::OutlinedArrowPath)
                        ->action(fn (PayrollRun $r, PayrollActions $a) => response()->streamDownload(
                            fn () => print ($a->bankCsv($r)), 'bank-transfer-' . str_replace(' ', '-', $r->label()) . '.csv', ['Content-Type' => 'text/csv'])),
                    Action::make('bankLetter')->label('Bank letter (PDF)')->icon(Heroicon::OutlinedEnvelope)
                        ->schema([TextInput::make('bank')->label('Bank / branch')->placeholder('HBL, Main Boulevard branch')])
                        ->action(fn (PayrollRun $r, PayrollActions $a, array $data) => response()->streamDownload(
                            fn () => print ($a->bankLetter($r, $data['bank'] ?? null)), 'bank-letter-' . str_replace(' ', '-', $r->label()) . '.pdf',
                            ['Content-Type' => 'application/pdf'])),
                    Action::make('regenerate')->label('Recalculate')->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn (PayrollRun $r) => $r->status === 'draft')
                        ->action(fn (PayrollRun $r, PayrollActions $a) => self::run(fn () => $a->generate($r->period, auth()->user()), 'Recalculated')),
                    Action::make('finalise')->label('Finalise & send payslips')->icon(Heroicon::OutlinedShieldCheck)->color('success')
                        ->visible(fn (PayrollRun $r) => $r->status === 'draft')->requiresConfirmation()
                        ->modalDescription('Advances are recovered, payslip PDFs generated and employees notified. This cannot be undone.')
                        ->action(fn (PayrollRun $r, PayrollActions $a) => self::run(fn () => $a->finalise($r, auth()->user()), 'Payroll finalised')),
                ]),
            ]);
    }

    public static function run(callable $fn, string $ok): void
    {
        try {
            $fn();
            Notification::make()->success()->title(__($ok))->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayrollRuns::route('/')];
    }
}
