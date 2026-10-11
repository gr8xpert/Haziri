<?php

declare(strict_types=1);

use App\Actions\Tenants\SaveBranding;
use App\Enums\DevicePunchSourceType;
use App\Enums\Role;
use App\Filament\Tenant\Pages\Billing;
use App\Filament\Tenant\Pages\Branding;
use App\Models\Central\DeviceRegistry;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PanelResetPassword;
use App\Services\Settings\TenantSettings;
use App\Support\Brand;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/* A white-labelled client (AASML) must never see the platform's name or icon. */

function whiteLabel(bool $on): void
{
    foreach (['white_label', 'ai_reports', 'kiosk', 'mobile_punch'] as $flag) {
        Setting::updateOrCreate(['key' => "feature.{$flag}"], ['value' => $on || $flag !== 'white_label']);
    }
    app(TenantSettings::class)->flush();
}

/** Text a person can read on the page: no scripts, styles or markup. */
function visibleText(string $html): string
{
    return strip_tags((string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html));
}

beforeEach(function () {
    whiteLabel(true);
    app(SaveBranding::class)(['app_name' => 'AASML Attendance', 'assistant_name' => 'Ask AASML', 'brand_color' => '#1d4ed8']);
    $this->base = 'http://pest.' . config('hazri.central_domain');
    $this->owner = User::where('email', 'owner@pest.test')->firstOrFail();
});

afterEach(fn () => DeviceRegistry::where('serial_number', 'KIOSK-WL')->delete());

it('shows no platform name or icon anywhere a white-label client can look', function () {
    $emp = Employee::create(['code' => 'WL1', 'first_name' => 'Wali', 'joined_on' => '2025-01-01']);
    $staff = User::create(['name' => 'Wali', 'email' => 'wali@pest.test', 'password' => 'secret123', 'employee_id' => $emp->id]);
    $staff->assignRole(Role::Employee->value);
    config(['hazri.neutral_domain' => 'neutral-cloud.pk']); // production: HAZRI_NEUTRAL_DOMAIN
    $device = Device::create(['name' => 'Tab', 'serial_number' => 'KIOSK-WL', 'type' => DevicePunchSourceType::Kiosk]);
    $kiosk = Kiosk::create(['device_id' => $device->id, 'token' => Kiosk::newToken(), 'allowed_modes' => ['pin', 'qr'], 'require_photo' => false]);

    $pages = [
        'admin login' => fn () => $this->get("{$this->base}/admin/login"),
        'dashboard' => fn () => $this->actingAs($this->owner)->get("{$this->base}/admin"),
        'AI assistant' => fn () => $this->actingAs($this->owner)->get("{$this->base}/admin/ask"),
        'branding' => fn () => $this->actingAs($this->owner)->get("{$this->base}/admin/branding"),
        'billing' => fn () => $this->actingAs($this->owner)->get("{$this->base}/admin/billing"),
        'profile' => fn () => $this->actingAs($this->owner)->get("{$this->base}/admin/profile"),
        'forgot password' => fn () => $this->get("{$this->base}/admin/password-reset/request"),
        'devices' => fn () => $this->actingAs($this->owner)->get("{$this->base}/admin/devices/create"),
        'employee app login' => fn () => $this->get("{$this->base}/me/login"),
        'employee app' => fn () => $this->actingAs($staff)->get("{$this->base}/me"),
        'app manifest' => fn () => $this->get("{$this->base}/manifest.webmanifest"),
        'kiosk' => fn () => $this->get("{$this->base}/kiosk/{$kiosk->token}"),
        'help' => fn () => $this->get("{$this->base}/help"),
        'help topic' => fn () => $this->get("{$this->base}/help/sync-agent"),
        'help quick reference' => fn () => $this->get("{$this->base}/help/quick"),
        'help troubleshooting' => fn () => $this->get("{$this->base}/help/troubleshooting"),
        'help search' => fn () => $this->get("{$this->base}/help/search.json"),
    ];
    foreach ($pages as $name => $request) {
        $html = $request()->assertOk()->getContent();
        // The platform name, old (Hazri) or current (Haziri) spelling.
        expect(preg_match('/haz(i)?ri/i', visibleText($html)))->toBe(0, "\"Haziri\" is visible on the {$name} page")
            ->and(str_contains($html, Brand::PLATFORM_ICON))->toBeFalse("the platform icon is used on the {$name} page");
        auth()->logout();
    }

    $this->actingAs($this->owner)->get("{$this->base}/admin/ask")->assertSee('Ask AASML');
    auth()->logout();
    $this->get("{$this->base}/me/login")->assertDontSee('Powered by');
    $this->get("{$this->base}/manifest.webmanifest")->assertJson(['name' => 'AASML Attendance', 'short_name' => 'AASML Attendance', 'theme_color' => '#1d4ed8']);
});

it('uses the platform brand for ordinary clients', function () {
    whiteLabel(false);

    expect(Brand::name())->toBe('Haziri')
        ->and(Brand::assistantName())->toBe('Ask Haziri')
        ->and(Brand::iconUrl())->toBe(Brand::PLATFORM_ICON);
    $this->get("{$this->base}/me/login")->assertSee('Powered by Haziri');
});

it('serves the client\'s uploaded icon and keeps billing for white-label clients', function () {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    app(SaveBranding::class)([], null, $png);

    expect(Brand::iconUrl())->toStartWith('/app-icon?v=');
    $this->get("{$this->base}/app-icon")->assertOk()->assertHeader('Content-Type', 'image/png');

    $this->actingAs($this->owner);
    expect(Billing::canAccess())->toBeTrue();
});

it('sends the forgot-password mail straight away under the client\'s name', function () {
    Notification::fake();
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('tenant'));

    Livewire\Livewire::test(RequestPasswordReset::class)
        ->set('data.email', 'owner@pest.test')->call('request')->assertHasNoErrors();

    Notification::assertSentTo($this->owner, PanelResetPassword::class, function (PanelResetPassword $sent) {
        $mail = $sent->toMail($this->owner);

        return ! $sent instanceof ShouldQueue
            && $mail->from[1] === 'AASML Attendance'
            && str_contains($mail->actionUrl, 'pest.');
    });
});

it('stops the client from changing branding the provider locked', function () {
    app(SaveBranding::class)([], null, null, true);
    $this->actingAs($this->owner);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('tenant'));

    Livewire\Livewire::test(Branding::class)
        ->set('data.app_name', 'Something else')->call('save')->assertHasErrors();

    app(TenantSettings::class)->flush();
    expect(Brand::name())->toBe('AASML Attendance');
});
