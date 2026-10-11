<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\AuditLogs;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

/** Read-only audit log viewer (spec §9 phase 1). */
class AuditLogResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = Activity::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    protected static string $viewPermission = 'audit.view';

    protected static string $managePermission = 'audit.manage_never';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('causer.name')->label('By')->placeholder('System'),
            TextEntry::make('event'),
            TextEntry::make('subject_type')->formatStateUsing(fn (?string $s) => $s ? class_basename($s) : '')->label('Record'),
            KeyValueEntry::make('attribute_changes.old')->label('Before')->columnSpanFull(),
            KeyValueEntry::make('attribute_changes.attributes')->label('After')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('causer.name')->label('By')->placeholder('System'),
                TextColumn::make('event')->badge()->formatStateUsing(fn (?string $state) => ucfirst(str_replace('_', ' ', (string) $state))),
                TextColumn::make('subject_type')->label('Record')->formatStateUsing(fn (?string $s) => $s ? class_basename($s) : ''),
                TextColumn::make('subject_id')->label('#'),
                TextColumn::make('description')->limit(40),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Record type')
                    ->options(fn () => Activity::query()->distinct()->pluck('subject_type')->filter()
                        ->mapWithKeys(fn ($t) => [$t => class_basename($t)])->all()),
                SelectFilter::make('event')
                    ->options(fn () => Activity::query()->distinct()->orderBy('event')->pluck('event')->filter()
                        ->mapWithKeys(fn (string $e) => [$e => ucfirst(str_replace('_', ' ', $e))])->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}
