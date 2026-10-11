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
use Filament\Schemas\Components\Component;
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
                Toggle::make('is_default')->label('Default shift')->helperText('Used for employees with no shift of their own')->inline(false),
                TimePicker::make('start_time')->seconds(false)->required(),
                TimePicker::make('end_time')->seconds(false)->required(),
                Toggle::make('crosses_midnight')->label('Ends the next day')->helperText('Night shift, for example 22:00–06:00')->inline(false),
                $minutes('break_min', 'Taken off the worked time')->label('Unpaid break')->default(60)->required(),
                Select::make('multi_punch_mode')->label('Punch mode')->options(MultiPunchMode::class)->default(MultiPunchMode::FirstLast)->required()
                    ->helperText('First in, last out: the day runs from the first to the last punch. Every in/out pair: break punches are subtracted.'),
            ]),
            Section::make(__('Late & early'))->columns(3)->schema([
                $minutes('grace_in_min', 'Arrivals within this many minutes of the start are on time')->label('Grace in')->default(15)->required(),
                $minutes('late_after_min', 'An arrival this long after the start counts as late. Blank = same as grace in')->label('Late after'),
                $minutes('half_day_if_late_after_min', 'Late by more than this makes the day a half day. Blank = never')->label('Half day if late by more than')->default(120),
                $minutes('grace_out_min', 'Early-out minutes are counted from the end time minus this')->label('Grace out')->default(5)->required(),
                $minutes('early_out_before_min', 'Leaving more than this before the end time is an early out. 0 = right after the grace out')->label('Early out before end')->default(30)->required(),
                $minutes('half_day_if_worked_less_than_min', 'The least worked time for a full day; less makes it a half day. Blank = never')->label('Half day if worked less than')->default(240),
            ]),
            Section::make(__('Other'))->columns(2)->schema([
                $minutes('auto_close_missing_out_after_min', 'Minutes after the first punch; the day is then closed as a full shift. Blank = never')->label('Auto-close a missing out after'),
                $minutes('overtime_after_min', 'Once the extra time over the shift is more than this, all of the extra time is overtime. Blank = any extra time')->label('Overtime after'),
                $minutes('max_overtime_min', 'Blank = no limit')->label('Max overtime per day'),
                CheckboxList::make('weekly_off_days')->label('Weekly off days')->options(BranchResource::WEEKDAYS)->columns(4)->columnSpanFull()
                    ->dehydrateStateUsing(fn (?array $state): ?array => filled($state) ? array_map('intval', array_values($state)) : null)
                    ->helperText('Leave empty to use the branch weekly offs.'),
            ]),
        ]);
    }

    /**
     * The few fields a new shift needs, for "+ New shift" on the roster; every other rule keeps its default.
     *
     * @return list<Component>
     */
    public static function quickCreateSchema(): array
    {
        return [
            CodeFromName::nameInput(Shift::class, 20)->required()->maxLength(100)->placeholder('Night'),
            TextInput::make('code')->required()->maxLength(20)->unique(Shift::class, 'code')->helperText('Filled in from the name; you can change it.'),
            TimePicker::make('start_time')->seconds(false)->required(),
            TimePicker::make('end_time')->seconds(false)->required(),
            Toggle::make('crosses_midnight')->label('Ends the next day (night shift)'),
            TextInput::make('break_min')->label('Unpaid break')->numeric()->integer()->minValue(0)->default(60)->suffix('min')->required(),
        ];
    }

    /** @param array<string, mixed> $data from quickCreateSchema() */
    public static function quickCreate(array $data): Shift
    {
        return Shift::create([
            'name' => $data['name'], 'code' => $data['code'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time'],
            'crosses_midnight' => (bool) ($data['crosses_midnight'] ?? false), 'break_min' => (int) ($data['break_min'] ?? 60),
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
