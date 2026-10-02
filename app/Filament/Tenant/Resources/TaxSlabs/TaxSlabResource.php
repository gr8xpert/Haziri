<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\TaxSlabs;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\TaxSlab;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Annual salary income-tax table per tax year (pre-filled for 2025-26 — check against FBR each budget). */
class TaxSlabResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = TaxSlab::class;

    protected static string $feature = 'payroll_tax';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Payroll;

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'tax slab';

    protected static string $viewPermission = 'payroll.manage';

    protected static string $managePermission = 'payroll.manage';

    public static function yearLabel(int $year): string
    {
        return $year . '-' . substr((string) ($year + 1), -2);
    }

    public static function form(Schema $schema): Schema
    {
        $rs = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric()->integer()->minValue(0)->prefix('Rs');

        return $schema->columns(2)->components([
            TextInput::make('tax_year')->numeric()->integer()->minValue(2020)->maxValue(2100)->required()
                ->default(now()->month >= 7 ? now()->year : now()->year - 1)->helperText('2025 = tax year July 2025 – June 2026'),
            $rs('from_amount', 'Annual income above')->required()->default(0),
            $rs('to_amount', 'Up to')->helperText('Blank = and above'),
            $rs('fixed_tax', 'Fixed tax')->default(0)->required(),
            TextInput::make('rate_percent')->label('Rate on the amount above')->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_amount')
            ->description('Rates for salaried individuals, pre-filled for tax year 2025-26. Check them against the FBR schedule every budget.')
            ->columns([
                TextColumn::make('tax_year')->label('Tax year')->formatStateUsing(fn (int $state) => self::yearLabel($state)),
                TextColumn::make('from_amount')->label('Income above')->prefix('Rs ')->numeric(),
                TextColumn::make('to_amount')->label('Up to')->prefix('Rs ')->numeric()->placeholder('and above'),
                TextColumn::make('fixed_tax')->label('Fixed tax')->prefix('Rs ')->numeric(),
                TextColumn::make('rate_percent')->label('Rate')->suffix('%'),
            ])
            ->filters([SelectFilter::make('tax_year')->label('Tax year')
                ->options(fn () => TaxSlab::query()->distinct()->orderByDesc('tax_year')->pluck('tax_year')->mapWithKeys(fn ($y) => [$y => self::yearLabel((int) $y)])->all())])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageTaxSlabs::route('/')];
    }
}
