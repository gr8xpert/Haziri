<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\Tenants\SaveBranding;
use App\Filament\Tenant\NavigationGroup;
use App\Services\Settings\TenantSettings;
use App\Support\Brand;
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
use Stancl\Tenancy\Database\Models\Domain;

/**
 * White-label + custom domains (spec §9 phase 4, plan feature `white_label`): logo, icon, colour, product and
 * assistant names, email sender name; company domains (CNAME to the platform; TLS is issued by operations).
 * The super-admin can lock this page.
 */
class Branding extends Page
{
    protected string $view = 'filament.tenant.pages.branding';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedPaintBrush;

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
        $this->form->fill(array_combine(SaveBranding::TEXT_KEYS, array_map(fn (string $k) => $settings->get($k), SaveBranding::TEXT_KEYS)));
    }

    public function isLocked(): bool
    {
        return (bool) app(TenantSettings::class)->get('branding_locked');
    }

    public function form(Schema $schema): Schema
    {
        $image = fn (string $name, string $label) => FileUpload::make($name)->label($label)->image()->disk('local')->directory('branding')
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])->maxSize(150);

        return $schema->statePath('data')->disabled(fn () => $this->isLocked())->components([
            Section::make(__('Name'))->columns(3)->schema([
                TextInput::make('app_name')->label(__('App name'))->maxLength(60)->placeholder(tenant('company_name') ?: __('Attendance'))
                    ->helperText(__('Shown in titles, the employee app, the kiosk, PDFs and messages.')),
                TextInput::make('assistant_name')->label(__('AI assistant name'))->maxLength(60)->placeholder(__('Ask AI')),
                TextInput::make('email_from_name')->label(__('Email sender name'))->maxLength(60)->placeholder(tenant('company_name')),
            ]),
            Section::make(__('Look'))->columns(3)->schema([
                $image('logo', __('Logo (PNG/JPEG/WebP, max 150 KB)')),
                $image('icon', __('App icon, square (PNG/JPEG/WebP, max 150 KB)'))->helperText(__('Browser tab and phone home screen. Falls back to the logo.')),
                ColorPicker::make('brand_color')->label(__('Theme colour'))->hex()->regex('/^#[0-9a-fA-F]{6}$/'),
            ]),
        ]);
    }

    public function save(SaveBranding $save): void
    {
        if ($this->isLocked()) {
            throw ValidationException::withMessages(['data.app_name' => __('Branding is managed by your provider.')]);
        }
        $state = $this->form->getState();
        $read = function (?string $path): ?string {
            if (empty($path)) {
                return null;
            }
            $bytes = (string) Storage::disk('local')->get($path);
            Storage::disk('local')->delete($path);

            return $bytes;
        };
        try {
            $save(array_intersect_key($state, array_flip(SaveBranding::TEXT_KEYS)), $read($state['logo'] ?? null), $read($state['icon'] ?? null));
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["data.{$k}" => $m])->all());
        }
        Notification::make()->success()->title(__('Branding saved'))->send();
    }

    /** The saved logo or icon as a data: URL, shown above the upload boxes. */
    public function currentImage(string $which): ?string
    {
        $value = app(TenantSettings::class)->get($which === 'icon' ? 'brand_icon' : 'brand_logo');

        return is_string($value) && str_starts_with($value, 'data:image/') ? $value : null;
    }

    public function removeImage(string $which, SaveBranding $save): void
    {
        if ($this->isLocked() || ! in_array($which, ['logo', 'icon'], true)) {
            return;
        }
        $save->removeImage($which);
        Notification::make()->success()->title($which === 'icon' ? __('App icon removed') : __('Logo removed'))->send();
    }

    /** Where the client points their CNAME: a neutral platform host when configured. */
    public function cnameTarget(): string
    {
        return Brand::host('cname');
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
        if (Domain::query()->where('domain', $host)->exists()) {
            throw ValidationException::withMessages(['newDomain' => __('This domain is already in use.')]);
        }
        tenant()->domains()->create(['domain' => $host]);
        $this->newDomain = '';
        Notification::make()->success()->title(__('Domain added'))
            ->body(__('Create a CNAME record :d → :t. HTTPS is enabled by our team within one working day.', ['d' => $host, 't' => $this->cnameTarget()]))
            ->persistent()->send();
    }

    public function removeDomain(string $host): void
    {
        tenant()->domains()->where('domain', $host)->where('domain', 'like', '%.%')->delete();
    }
}
