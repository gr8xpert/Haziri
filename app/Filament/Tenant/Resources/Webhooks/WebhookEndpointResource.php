<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Webhooks;

use App\Filament\Concerns\AuthorizesByPermission;
use App\Filament\Concerns\RequiresFeature;
use App\Filament\Tenant\NavigationGroup;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\Webhooks;
use App\Support\Security\PublicUrl;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Outgoing webhooks (spec §13.3): HMAC-signed, retried, public URLs only. */
class WebhookEndpointResource extends Resource
{
    use AuthorizesByPermission, RequiresFeature;

    protected static ?string $model = WebhookEndpoint::class;

    protected static string $feature = 'public_api';

    protected static ?string $slug = 'webhooks';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedLink;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 61;

    protected static ?string $navigationLabel = 'Webhooks';

    protected static string $viewPermission = 'settings.manage';

    protected static string $managePermission = 'settings.manage';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('url')->url()->required()->maxLength(500)->columnSpanFull()
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if (($problem = PublicUrl::check((string) $value)) !== null) {
                        $fail($problem);
                    }
                }),
            CheckboxList::make('events')->options(array_combine(WebhookEndpoint::EVENTS, WebhookEndpoint::EVENTS))->required()->columns(2),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->limit(50),
                TextColumn::make('events')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('deliveries_count')->counts('deliveries')->label('Deliveries'),
            ])
            ->recordActions([
                Action::make('ping')->label('Send test')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->action(function (WebhookEndpoint $record) {
                        $delivery = $record->deliveries()->create(['event' => 'ping', 'payload' => [
                            'id' => (string) Str::uuid(), 'event' => 'ping', 'tenant' => tenant('id'), 'created_at' => now()->toIso8601String(), 'data' => [],
                        ]]);
                        DeliverWebhook::dispatch($delivery->id);
                        Notification::make()->success()->title(__('Test delivery queued'))->send();
                    }),
                Action::make('secret')->label('Show secret')->icon(Heroicon::OutlinedShieldCheck)->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (WebhookEndpoint $record) => Notification::make()->persistent()->title(__('Signing secret'))
                        ->body($record->secret . "\n" . __('Each request carries the signature header shown below. Check that X-Webhook-Signature = sha256=HMAC_SHA256(secret, "{X-Webhook-Timestamp}.{raw body}").'))->send()),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageWebhookEndpoints::route('/')];
    }
}
