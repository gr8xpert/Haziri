<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Models\Setting;
use App\Services\Settings\TenantSettings;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * White-label + custom domains (spec §9 phase 4, plan feature `white_label`): logo, colour and email sender name;
 * company domains pointing at Hazri (CNAME to the {slug} subdomain; TLS is issued by operations).
 */
class Branding extends Page
{
    protected string $view = 'filament.tenant.pages.branding';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedSun;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Branding & domain';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public string $newDomain = '';

    public static function canAccess(): bool
    {
        return app(TenantSettings::class)->featureEnabled('white_label') && (bool) auth()->user()?->can('settings.manage');
    }

    public function mount(TenantSettings $settings): void
    {
        $this->form->fill(['brand_color' => $settings->get('brand_color'), 'email_from_name' => $settings->get('email_from_name')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('Look'))->columns(3)->schema([
                FileUpload::make('logo')->label(__('Logo (PNG/JPEG/WebP, max 150 KB)'))->image()->disk('local')->directory('branding')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(150),
                ColorPicker::make('brand_color')->hex()->regex('/^#[0-9a-fA-F]{6}$/'),
                TextInput::make('email_from_name')->maxLength(60)->placeholder(tenant('company_name')),
            ]),
        ]);
    }

    public function save(TenantSettings $settings): void
    {
        $state = $this->form->getState();
        if (! empty($state['logo'])) {
            $bytes = Storage::disk('local')->get($state['logo']);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) $bytes);
            if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || strlen((string) $bytes) > 150_000) {
                throw ValidationException::withMessages(['data.logo' => __('Use a PNG, JPEG or WebP under 150 KB.')]);
            }
            // Stored inline so every panel / PWA / email can show it without a public, tenant-shared disk.
            Setting::updateOrCreate(['key' => 'brand_logo'], ['value' => "data:{$mime};base64," . base64_encode((string) $bytes)]);
            Storage::disk('local')->delete($state['logo']);
        }
        foreach (['brand_color', 'email_from_name'] as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $state[$key] ?: null]);
        }
        $settings->flush();
        Notification::make()->success()->title(__('Branding saved'))->send();
    }

    /** @return list<string> */
    public function getDomains(): array
    {
        return tenant()->domains()->pluck('domain')->filter(fn ($d) => str_contains($d, '.'))->values()->all();
    }

    public function addDomain(): void
    {
        $host = strtolower(trim($this->newDomain));
        $central = config('hazri.central_domain');
        $valid = preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1;
        if (! $valid || $host === $central || str_ends_with($host, '.' . $central)) {
            throw ValidationException::withMessages(['newDomain' => __('Enter a domain you own, like attendance.yourcompany.pk.')]);
        }
        if (\Stancl\Tenancy\Database\Models\Domain::query()->where('domain', $host)->exists()) {
            throw ValidationException::withMessages(['newDomain' => __('This domain is already in use.')]);
        }
        tenant()->domains()->create(['domain' => $host]);
        $this->newDomain = '';
        Notification::make()->success()->title(__('Domain added'))
            ->body(__('Create a CNAME record :d → :t. HTTPS is enabled by our team within one working day.', ['d' => $host, 't' => tenant('slug') . '.' . $central]))
            ->persistent()->send();
    }

    public function removeDomain(string $host): void
    {
        tenant()->domains()->where('domain', $host)->where('domain', 'like', '%.%')->delete();
    }
}
