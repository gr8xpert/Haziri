<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Contractors;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Contractor;
use App\Support\CodeFromName;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Labour contractors that supply contract staff; employees link to one and reports filter by it. */
class ContractorResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Contractor::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Organisation;

    protected static ?int $navigationSort = 50;

    protected static string $viewPermission = 'organisation.manage';

    protected static string $managePermission = 'organisation.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            CodeFromName::nameInput(Contractor::class, 20)->required()->maxLength(255),
            TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true)->helperText('Filled in from the name; you can change it.'),
            TextInput::make('phone')->tel()->maxLength(20),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone'),
                TextColumn::make('employees_count')->counts('employees')->label('Employees'),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageContractors::route('/')];
    }
}
