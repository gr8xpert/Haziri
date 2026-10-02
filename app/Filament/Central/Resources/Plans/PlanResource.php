<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Plans;

use App\Filament\Central\FeatureToggles;
use App\Models\Central\Plan;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(30),
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('price_per_employee')->numeric()->integer()->prefix('Rs')->required()->default(0),
            TextInput::make('price_fixed')->numeric()->integer()->prefix('Rs')->required()->default(0),
            TextInput::make('limits.employees')->numeric()->integer()->label('Employee limit')->helperText('Blank = unlimited'),
            Toggle::make('is_active')->default(true),
            // Stored as {feature: bool}: the defaults a new company on this plan starts with.
            Group::make(FeatureToggles::schema('feature_flags'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code'),
                TextColumn::make('name'),
                TextColumn::make('price_per_employee')->prefix('Rs '),
                TextColumn::make('limits.employees')->label('Employees')->placeholder('∞'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePlans::route('/')];
    }
}
