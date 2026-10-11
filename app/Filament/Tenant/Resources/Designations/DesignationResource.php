<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Designations;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\Designation;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DesignationResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Designation::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::Organisation;

    protected static ?int $navigationSort = 30;

    protected static string $viewPermission = 'organisation.manage';

    protected static string $managePermission = 'organisation.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDesignations::route('/')];
    }
}
