<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Holidays;

use App\Enums\HolidayType;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Holiday;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HolidayResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Holiday::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 30;

    protected static string $viewPermission = 'holidays.manage';

    protected static string $managePermission = 'holidays.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('date')->required(),
            TextInput::make('name')->required()->maxLength(255),
            Select::make('type')->options(HolidayType::class)->default(HolidayType::Company)->required(),
            Select::make('branch_id')->relationship('branch', 'name')->placeholder('All branches'),
            Toggle::make('is_paid')->default(true),
            Toggle::make('needs_confirmation')->helperText('Provisional date (moon sighting)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->date('D, d M Y')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('branch.name')->placeholder('All'),
                IconColumn::make('is_paid')->boolean(),
                IconColumn::make('needs_confirmation')->boolean()->label('Provisional')
                    ->trueIcon(Heroicon::OutlinedExclamationTriangle)->trueColor('warning')->falseIcon(null),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->options(fn () => collect(range(now()->year - 1, now()->year + 1))->mapWithKeys(fn ($y) => [$y => $y])->all())
                    ->default((string) now()->year)
                    ->query(fn (Builder $q, array $data) => $data['value'] ? $q->whereYear('date', $data['value']) : $q),
                TernaryFilter::make('needs_confirmation')->label('Provisional'),
            ])
            ->recordActions([
                Action::make('confirm')->icon(Heroicon::OutlinedShieldCheck)->color('success')
                    ->visible(fn (Holiday $r) => $r->needs_confirmation)
                    ->action(fn (Holiday $r) => $r->update(['needs_confirmation' => false])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageHolidays::route('/')];
    }
}
