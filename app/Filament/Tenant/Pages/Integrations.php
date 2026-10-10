<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Models\Setting;
use App\Services\Notifications\ChannelRegistry;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;
use App\Services\Settings\TenantSettings;
use App\Support\Brand;
use App\Support\Security\PublicUrl;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;

/** WhatsApp (Meta Cloud API) and SMS gateway credentials, stored encrypted per tenant (spec §11, §15). */
class Integrations extends Page
{
    protected string $view = 'filament.tenant.pages.company-settings';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'WhatsApp & SMS';

    private const PLAIN = ['whatsapp_provider', 'whatsapp_meta_phone_number_id', 'whatsapp_meta_api_version', 'sms_provider',
        'sms_http_url', 'sms_http_method', 'sms_http_params', 'sms_http_sender', 'sms_http_success_contains'];

    private const SECRET = ['whatsapp_meta_token', 'sms_http_api_key'];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.manage');
    }

    public function mount(TenantSettings $settings): void
    {
        $values = array_intersect_key($settings->all(), array_flip(self::PLAIN));
        $this->form->fill($values + ['whatsapp_provider' => 'log', 'sms_provider' => 'log', 'whatsapp_meta_api_version' => 'v21.0',
            'sms_http_method' => 'POST', 'sms_http_params' => ['to' => '{to}', 'message' => '{message}', 'api_key' => '{api_key}']]);
    }

    public function form(Schema $schema): Schema
    {
        $is = fn (string $field, string $value) => fn (Get $get) => $get($field) === $value;

        return $schema->statePath('data')->components([
            Section::make('WhatsApp')->columns(2)->description('Needs the WhatsApp module enabled for your plan.')->schema([
                Select::make('whatsapp_provider')->options(['log' => 'Not set up (log only)', 'meta' => 'Meta WhatsApp Cloud API'])->live(),
                TextInput::make('whatsapp_meta_phone_number_id')->label('Phone number ID')->visible($is('whatsapp_provider', 'meta')),
                TextInput::make('whatsapp_meta_token')->label('Permanent access token')->password()->revealable()
                    ->placeholder('•••• saved — leave blank to keep')->visible($is('whatsapp_provider', 'meta')),
                TextInput::make('whatsapp_meta_api_version')->label('Graph API version')->visible($is('whatsapp_provider', 'meta')),
            ]),
            Section::make('SMS')->columns(2)->schema([
                Select::make('sms_provider')->options(['log' => 'Not set up (log only)', 'http' => 'HTTP gateway'])->live(),
                TextInput::make('sms_http_url')->url()->label('Gateway URL')->visible($is('sms_provider', 'http'))
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                        if (filled($value) && ($problem = PublicUrl::check((string) $value)) !== null) {
                            $fail($problem);
                        }
                    }),
                Select::make('sms_http_method')->options(['POST' => 'POST', 'GET' => 'GET'])->visible($is('sms_provider', 'http')),
                TextInput::make('sms_http_sender')->label('Sender ID / mask')->visible($is('sms_provider', 'http')),
                TextInput::make('sms_http_api_key')->label('API key / password')->password()->revealable()
                    ->placeholder('•••• saved — leave blank to keep')->visible($is('sms_provider', 'http')),
                TextInput::make('sms_http_success_contains')->label('Success response contains')->visible($is('sms_provider', 'http')),
                KeyValue::make('sms_http_params')->label('Request parameters')->columnSpanFull()
                    ->helperText('Values may use {to}, {message}, {sender}, {api_key}.')->visible($is('sms_provider', 'http')),
            ]),
        ]);
    }

    public function save(TenantSettings $settings): void
    {
        $state = $this->form->getState();
        foreach (self::PLAIN as $key) {
            if (array_key_exists($key, $state)) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $state[$key]]);
            }
        }
        foreach (self::SECRET as $key) {
            if (filled($state[$key] ?? null)) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => Crypt::encryptString((string) $state[$key])]);
            }
        }
        $settings->flush();
        Notification::make()->success()->title(__('Saved'))->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')->label(__('Send test message'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->schema([
                    Select::make('channel')->options(['whatsapp' => 'WhatsApp', 'sms' => 'SMS'])->required(),
                    TextInput::make('phone')->tel()->required()->placeholder('03xx xxxxxxx'),
                ])
                ->action(function (array $data, ChannelRegistry $channels) {
                    $driver = $channels->resolve($data['channel']);
                    if ($driver === null) {
                        Notification::make()->danger()->title(__('This channel is disabled for your company.'))->send();

                        return;
                    }
                    $r = $driver->send(new Recipient('Test', null, $data['phone']),
                        new RenderedMessage('test', null, __(':app test message from :c', ['app' => Brand::name(), 'c' => tenant('company_name')])));
                    $r->ok
                        ? Notification::make()->success()->title(__('Sent'))->body($r->providerRef)->send()
                        : Notification::make()->danger()->title(__('Failed'))->body($r->error)->persistent()->send();
                }),
        ];
    }
}
