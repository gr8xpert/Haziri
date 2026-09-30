<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\SalaryStructures;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Employee;
use App\Models\SalaryStructure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalaryStructureResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = SalaryStructure::class;

    protected static string $feature = 'payroll_lite';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Payroll;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Salary structures';

    protected static string $viewPermission = 'payroll.manage';

    protected static string $managePermission = 'payroll.manage';

    public static function form(Schema $schema): Schema
    {
        $money = fn (string $n, string $label) => TextInput::make($n)->label($label)->numeric()->integer()->minValue(0)->prefix('Rs');
        $lines = fn (string $n, string $label) => Repeater::make($n)->label($label)->columns(2)->defaultItems(0)->schema([
            TextInput::make('name')->required(), TextInput::make('amount')->numeric()->integer()->minValue(0)->prefix('Rs')->required(),
        ]);

        return $schema->columns(3)->components([
            Select::make('employee_id')->label('Employee')->required()->searchable()
                ->options(fn () => Employee::orderBy('code')->get()->mapWithKeys(fn (Employee $e) => [$e->id => "{$e->code} — {$e->fullName()}"])),
            DatePicker::make('effective_from')->required()->default(now()->startOfMonth()),
            $money('basic', 'Basic (monthly)')->default(0)->required(),
            $money('per_day_rate', 'Per-day rate (daily wage)'),
            $money('overtime_rate_normal', 'OT rate / hour'),
            $money('overtime_rate_holiday', 'Holiday OT rate / hour'),
            $lines('allowances', 'Allowances')->columnSpan(3),
            $lines('deductions_fixed', 'Fixed deductions (EOBI, loan…)')->columnSpan(3),
            TextInput::make('bank_name'),
            TextInput::make('bank_account')->label('Account / IBAN'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('employee.code')->label('Code')->searchable(),
                TextColumn::make('employee.first_name')->label('Employee')->formatStateUsing(fn (SalaryStructure $r) => $r->employee?->fullName()),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('basic')->prefix('Rs ')->numeric(),
                TextColumn::make('per_day_rate')->prefix('Rs ')->numeric()->placeholder('—'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSalaryStructures::route('/')];
    }
}
