<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\FinalSettlements;

use App\Actions\Payroll\FinalSettlementActions;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\FinalSettlement;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/** Full and final settlements for leaving employees: calculate, add lines, finalise, print. */
class FinalSettlementResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = FinalSettlement::class;

    protected static string $feature = 'payroll_settlement';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserMinus;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Payroll;

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'final settlement';

    protected static string $viewPermission = 'payroll.manage';

    protected static string $managePermission = 'payroll.manage';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (FinalSettlement $r) => "{$r->employee?->code} — {$r->employee?->fullName()}"),
                TextColumn::make('last_working_day')->date(),
                TextColumn::make('net')->label('Net payable')->prefix('Rs ')->numeric(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'final' ? 'success' : 'warning'),
            ])
            ->recordActions([
                Action::make('details')->icon(Heroicon::OutlinedEye)->color('gray')->modalSubmitAction(false)
                    ->modalContent(fn (FinalSettlement $r) => self::linesHtml($r)),
                Action::make('lines')->label('Add / edit lines')->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (FinalSettlement $r) => $r->status === 'draft')
                    ->fillForm(fn (FinalSettlement $r) => [
                        'manual' => array_values(array_filter($r->lines, fn ($l) => ($l['code'] ?? '') === 'manual')),
                        'notes' => $r->notes,
                    ])
                    ->schema([
                        Repeater::make('manual')->label('Extra lines (notice pay, bonus, damages…)')->columns(3)->defaultItems(0)->schema([
                            TextInput::make('label')->required(),
                            Select::make('type')->options(['earning' => 'Pay', 'deduction' => 'Deduct'])->default('earning')->required(),
                            TextInput::make('amount')->numeric()->integer()->minValue(0)->prefix('Rs')->required(),
                        ]),
                        Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (FinalSettlement $r, array $data, FinalSettlementActions $actions) {
                        $actions->updateManualLines($r, $data['manual'] ?? []);
                        $r->update(['notes' => $data['notes'] ?? null]);
                        Notification::make()->success()->title(__('Settlement updated'))->send();
                    }),
                Action::make('recalculate')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->visible(fn (FinalSettlement $r) => $r->status === 'draft')
                    ->action(fn (FinalSettlement $r, FinalSettlementActions $actions) => self::run(
                        fn () => $actions->calculate($r->employee, $r->last_working_day->format('Y-m-d'), auth()->user()), __('Recalculated'))),
                Action::make('finalise')->icon(Heroicon::OutlinedShieldCheck)->color('success')->requiresConfirmation()
                    ->modalDescription(__('Outstanding advances are closed and the provident fund balance is paid out. This cannot be undone.'))
                    ->visible(fn (FinalSettlement $r) => $r->status === 'draft')
                    ->action(fn (FinalSettlement $r, FinalSettlementActions $actions) => self::run(fn () => $actions->finalise($r, auth()->user()), __('Settlement finalised'))),
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function (FinalSettlement $r, FinalSettlementActions $actions) {
                        $path = $r->pdf_path && Storage::disk('local')->exists($r->pdf_path) ? $r->pdf_path : $actions->pdf($r);

                        return response()->download(Storage::disk('local')->path($path));
                    }),
            ]);
    }

    public static function linesHtml(FinalSettlement $r): HtmlString
    {
        $rows = collect($r->lines)->map(fn ($l) => '<tr><td class="py-1 pe-4">' . e($l['label']) . '</td><td class="py-1 text-end">'
            . ($l['type'] === 'deduction' ? '− ' : '') . 'Rs ' . number_format($l['amount']) . '</td></tr>')->implode('');

        return new HtmlString('<table class="w-full text-sm">' . $rows . '<tr class="font-semibold border-t"><td class="py-2">'
            . e(__('Net payable')) . '</td><td class="py-2 text-end">Rs ' . number_format($r->net) . '</td></tr></table>');
    }

    public static function run(callable $fn, string $ok): void
    {
        try {
            $fn();
            Notification::make()->success()->title($ok)->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFinalSettlements::route('/')];
    }
}
