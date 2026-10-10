<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\ApiTokens;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Models\ApiToken;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApiTokenResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = ApiToken::class;

    protected static string $feature = 'public_api';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedKey;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'API tokens';

    protected static string $viewPermission = 'settings.manage';

    protected static string $managePermission = 'settings.manage';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('abilities')->badge(),
                TextColumn::make('last_used_at')->since()->placeholder('never'),
                TextColumn::make('revoked_at')->dateTime()->placeholder('active'),
            ])
            ->recordActions([
                Action::make('revoke')->color('danger')->icon(Heroicon::OutlinedExclamationTriangle)->requiresConfirmation()
                    ->visible(fn (ApiToken $r) => $r->revoked_at === null)
                    ->action(fn (ApiToken $r) => $r->update(['revoked_at' => now()])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListApiTokens::route('/')];
    }
}
