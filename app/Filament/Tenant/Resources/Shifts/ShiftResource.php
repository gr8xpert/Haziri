<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Shifts;

use App\Enums\MultiPunchMode;
use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Filament\Tenant\Resources\Branches\BranchResource;
use App\Models\Shift;
use App\Support\CodeFromName;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Shift::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::TimeRules;

    protected static ?int $navigationSort = 10;

    protected static string $viewPermission = 'shifts.manage';

    protected static string $managePermission = 'shifts.manage';

    public static function form(Schema $schema): Schema
    {
        $minutes = fn (string $name, ?string $help = null) => TextInput::make($name)->numeric()->integer()->minValue(0)->maxValue(1440)
            ->suffix('min')->helperText($help);

        return $schema->columns(2)->components([
            Section::make(__('Timing'))->columns(3)->columnSpanFull()->schema([
                CodeFromName::nameInput(Shift::class, 20)->required()->maxLength(100),
                TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true)->helperText('Filled in from the name; you can change it.'),
                Toggle::make('is_default')->helperText('Used for employees with no assignment')->inline(false),
                TimePicker::make('start_time')->seconds(false)->required(),
                TimePicker::make('end_time')->seconds(false)->required(),
                Toggle::make('crosses_midnight')->helperText('Night shift ending the next day')->inline(false),
                $minutes('break_min', 'Unpaid break')->default(60)->required(),
                Select::make('multi_punch_mode')->options(MultiPunchMode::class)->default(MultiPunchMode::FirstLast)->required()
                    ->helperText('First in, last out: the day runs from the first to the last punch. Every in/out pair: break punches are subtracted.'),
            ]),
            Section::make(__('Late & early'))->columns(3)->schema([
                $minutes('grace_in_min', 'Arrivals within this are on time')->default(15)->required(),
                $minutes('late_after_min', 'Blank = same as grace'),
                $minutes('half_day_if_late_after_min', 'Late minutes that make it a half day')->default(120),
                $minutes('grace_out_min')->default(5)->required(),
                $minutes('early_out_before_min')->default(30)->required(),
                $minutes('half_day_if_worked_less_than_min', 'Worked less → half day')->default(240),
            ]),
            Section::make(__('Other'))->columns(2)->schema([
                $minutes('min_full_day_min')->default(480)->required(),
                $minutes('auto_close_missing_out_after_min', 'Blank = never auto-close a missing punch-out'),
                $minutes('overtime_after_min', 'Extra time beyond this counts as overtime'),
                $minutes('max_overtime_min'),
                CheckboxList::make('weekly_off_days')->options(BranchResource::WEEKDAYS)->columns(4)->columnSpanFull()
                    ->helperText('Leave empty to use the branch weekly offs.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('start_time')->time('H:i'),
                TextColumn::make('end_time')->time('H:i'),
                TextColumn::make('grace_in_min')->suffix(' min')->label('Grace'),
                TextColumn::make('break_min')->suffix(' min')->label('Break'),
                IconColumn::make('crosses_midnight')->boolean()->label('Night'),
                IconColumn::make('is_default')->boolean()->label('Default'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }
}
