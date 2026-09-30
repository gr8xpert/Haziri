<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Branches;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Branch;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Branch::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Organisation;

    protected static ?int $navigationSort = 10;

    protected static string $viewPermission = 'organisation.manage';

    protected static string $managePermission = 'organisation.manage';

    public const WEEKDAYS = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('address')->columnSpanFull(),
            TextInput::make('lat')->numeric()->minValue(-90)->maxValue(90)->label('Latitude'),
            TextInput::make('lng')->numeric()->minValue(-180)->maxValue(180)->label('Longitude'),
            TextInput::make('geofence_radius_m')->numeric()->integer()->minValue(20)->maxValue(5000)->default(150)->suffix('m'),
            Select::make('timezone')->options(fn () => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->searchable()->placeholder('Company timezone'),
            CheckboxList::make('weekly_off_days')->options(self::WEEKDAYS)->columns(4)->columnSpanFull()
                ->helperText('Used when the shift has no weekly offs of its own. Default: Sunday.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('employees_count')->counts('employees')->label('Employees'),
                TextColumn::make('geofence_radius_m')->suffix(' m')->label('Geofence'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBranches::route('/')];
    }
}
