<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Advances;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Advance;
use App\Models\Employee;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Advances register (spec §9 phase 3). Recovered in equal instalments when a payroll run is finalised. */
class AdvanceResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = Advance::class;

    protected static string $feature = 'payroll_lite';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Payroll;

    protected static ?int $navigationSort = 20;

    protected static string $viewPermission = 'payroll.manage';

    protected static string $managePermission = 'payroll.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('employee_id')->label('Employee')->required()->searchable()->disabledOn('edit')
                ->options(fn () => Employee::orderBy('code')->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])),
            DatePicker::make('issued_on')->required()->default(now()),
            TextInput::make('amount')->numeric()->integer()->minValue(1)->prefix('Rs')->required()
                ->disabled(fn (?Advance $record) => $record !== null && ! $record->nothingRecovered())
                ->helperText(fn (?Advance $record) => $record !== null && ! $record->nothingRecovered() ? __('Part of it has been recovered, so the amount cannot change.') : null),
            TextInput::make('instalments')->numeric()->integer()->minValue(1)->maxValue(60)->default(1)->required(),
            TextInput::make('note')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issued_on', 'desc')
            ->columns([
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (Advance $r) => $r->employee?->fullName()),
                TextColumn::make('issued_on')->date(),
                TextColumn::make('amount')->prefix('Rs ')->numeric(),
                TextColumn::make('instalments'),
                TextColumn::make('remaining')->prefix('Rs ')->numeric(),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'settled' ? 'success' : 'warning'),
            ])
            ->filters([SelectFilter::make('status')->options(['active' => 'active', 'settled' => 'settled'])->default('active')])
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data, Advance $record): array => $record->nothingRecovered() && isset($data['amount'])
                    ? $data + ['remaining' => (int) $data['amount']] : $data),
                DeleteAction::make()->visible(fn (Advance $record) => $record->nothingRecovered()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAdvances::route('/')];
    }
}
