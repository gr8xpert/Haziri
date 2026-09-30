<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ShiftOverrides;

use App\Enums\ShiftOverrideScope;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Filament\Tenant\Resources\Branches\BranchResource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Shift;
use App\Models\ShiftOverride;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Ramadan timings, Jummah break, seasonal hours (spec §5.3). */
class ShiftOverrideResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = ShiftOverride::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedSun;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'timing override';

    protected static string $viewPermission = 'shifts.manage';

    protected static string $managePermission = 'shifts.manage';

    public static function form(Schema $schema): Schema
    {
        $min = fn (string $n) => TextInput::make($n)->numeric()->integer()->minValue(0)->maxValue(1440)->suffix('min');

        return $schema->columns(3)->components([
            TextInput::make('name')->required()->placeholder('Ramadan 2027')->columnSpan(2),
            TextInput::make('priority')->numeric()->integer()->default(10)->required()->helperText('Higher wins'),
            DatePicker::make('from_date')->required(),
            DatePicker::make('to_date')->required()->afterOrEqual('from_date'),
            Select::make('weekday')->options(BranchResource::WEEKDAYS)->placeholder('Every day')
                ->helperText('Pick Friday for a Jummah override'),
            Select::make('scope')->options(ShiftOverrideScope::class)->default(ShiftOverrideScope::Tenant)->required()->live(),
            Select::make('scope_id')->label('Applies to')
                ->options(fn (Get $get) => match ($get('scope') instanceof ShiftOverrideScope ? $get('scope') : ShiftOverrideScope::tryFrom((string) $get('scope'))) {
                    ShiftOverrideScope::Branch => Branch::pluck('name', 'id'),
                    ShiftOverrideScope::Department => Department::pluck('name', 'id'),
                    ShiftOverrideScope::Shift => Shift::pluck('name', 'id'),
                    default => [],
                })
                ->hidden(fn (Get $get) => in_array($get('scope'), [ShiftOverrideScope::Tenant, 'tenant', null], true))
                ->required(fn (Get $get) => ! in_array($get('scope'), [ShiftOverrideScope::Tenant, 'tenant', null], true)),
            Section::make(__('Changes (leave blank to keep the shift value)'))->columns(4)->columnSpanFull()->schema([
                TimePicker::make('start_time')->seconds(false),
                TimePicker::make('end_time')->seconds(false),
                $min('break_min'),
                $min('grace_in_min'),
                $min('grace_out_min'),
                $min('late_after_min'),
                $min('half_day_if_late_after_min'),
                $min('half_day_if_worked_less_than_min'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_date', 'desc')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('from_date')->date(),
                TextColumn::make('to_date')->date(),
                TextColumn::make('scope')->badge(),
                TextColumn::make('weekday')->formatStateUsing(fn (?int $state) => $state === null ? '' : BranchResource::WEEKDAYS[$state]),
                TextColumn::make('start_time')->time('H:i'),
                TextColumn::make('end_time')->time('H:i'),
                TextColumn::make('priority'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageShiftOverrides::route('/')];
    }
}
