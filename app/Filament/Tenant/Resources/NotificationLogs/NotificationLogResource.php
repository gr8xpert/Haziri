<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\NotificationLogs;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\NotificationLog;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Every notification attempt (spec §11: "Every send logged in notification_logs"). */
class NotificationLogResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = NotificationLog::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Notification log';

    protected static string $viewPermission = 'settings.manage';

    protected static string $managePermission = 'settings.manage_never';

    /** @var array<string, string> */
    private const CHANNELS = ['mail' => 'Email', 'database' => 'In-app', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'log' => 'Log'];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('event')->searchable()->formatStateUsing(fn (string $state) => Str::headline($state)),
                TextColumn::make('channel')->badge()->formatStateUsing(fn (string $state) => self::CHANNELS[$state] ?? $state),
                TextColumn::make('recipient')->searchable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'sent' => 'success', 'failed' => 'danger', 'queued' => 'warning', default => 'gray',
                }),
                TextColumn::make('attempts'),
                TextColumn::make('error')->limit(40),
            ])
            ->filters([
                SelectFilter::make('status')->options(['queued' => 'queued', 'sent' => 'sent', 'failed' => 'failed', 'skipped' => 'skipped']),
                SelectFilter::make('channel')->options(self::CHANNELS),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListNotificationLogs::route('/')];
    }
}
