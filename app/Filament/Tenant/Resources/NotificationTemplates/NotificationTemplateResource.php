<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\NotificationTemplates;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Tenant\NavigationGroup;
use App\Models\NotificationTemplate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Tenant overrides of the default texts in config/notification_templates.php (spec §11). */
class NotificationTemplateResource extends Resource
{
    use AuthorizesByPermission;

    protected static ?string $model = NotificationTemplate::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Notification texts';

    protected static string $viewPermission = 'settings.manage';

    protected static string $managePermission = 'settings.manage';

    public static function form(Schema $schema): Schema
    {
        $events = array_keys(config('notification_templates'));

        return $schema->columns(3)->components([
            Select::make('event')->options(array_combine($events, $events))->required()->live()
                ->afterStateUpdated(function ($state, $set, $get) {
                    [$subject, $body] = config("notification_templates.{$state}." . ($get('locale') ?: 'en'), ['', '']);
                    $set('subject', $subject);
                    $set('body', $body);
                }),
            Select::make('channel')->options(['mail' => 'Email / in-app', 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'database' => 'In-app only'])->required()->default('mail'),
            Select::make('locale')->options(['en' => 'English', 'ur' => 'اردو'])->default('en')->required(),
            TextInput::make('subject')->columnSpanFull(),
            Textarea::make('body')->required()->rows(4)->columnSpanFull()
                ->helperText('Placeholders like {{ employee }}, {{ from }}, {{ date }} are filled automatically.'),
            TextInput::make('provider_template')->label('WhatsApp approved template name')->helperText('Required by Meta for business-initiated messages'),
            Toggle::make('enabled')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')->searchable(),
                TextColumn::make('channel')->badge(),
                TextColumn::make('locale'),
                TextColumn::make('subject')->limit(40),
                IconColumn::make('enabled')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->label('Reset to default')]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNotificationTemplates::route('/')];
    }
}
