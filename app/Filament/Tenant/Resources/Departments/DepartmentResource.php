<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Departments;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Department;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Department::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Organisation;

    protected static ?int $navigationSort = 20;

    protected static string $viewPermission = 'organisation.manage';

    protected static string $managePermission = 'organisation.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('code')->required()->maxLength(20)->unique(ignoreRecord: true),
            Select::make('parent_id')->relationship('parent', 'name')->searchable()->preload()->label('Parent department'),
            Select::make('head_employee_id')->relationship('head', 'first_name')->searchable()->preload()->label('Head'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('Parent'),
                TextColumn::make('head.first_name')->label('Head'),
                TextColumn::make('employees_count')->counts('employees')->label('Employees'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDepartments::route('/')];
    }
}
