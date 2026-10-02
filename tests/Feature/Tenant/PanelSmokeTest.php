<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Features\FeatureCatalog;
use App\Services\Settings\TenantSettings;
use Filament\Facades\Filament;

/*
| Renders every tenant-panel page as the owner, so a broken form/table/page definition fails CI
| instead of the receptionist's browser.
*/
beforeEach(function () {
    $this->owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $host = 'pest.' . config('hazri.central_domain');
    $this->base = "http://{$host}";
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    // Every optional module on, so every screen gets rendered.
    foreach (FeatureCatalog::fromConfig()->keys() as $flag) {
        Setting::updateOrCreate(['key' => "feature.{$flag}"], ['value' => true]);
    }
    app(TenantSettings::class)->flush();
});

it('renders every tenant panel page for the owner', function () {
    $panel = Filament::getPanel('tenant');
    $this->actingAs($this->owner);
    $urls = ['/admin'];
    foreach ($panel->getResources() as $resource) {
        if (! $resource::canAccess()) {
            continue; // e.g. kiosk screen while the kiosk feature is off
        }
        if ($resource::hasPage('index')) {
            $urls[] = parse_url($resource::getUrl('index'), PHP_URL_PATH);
        }
        if ($resource::hasPage('create')) {
            $urls[] = parse_url($resource::getUrl('create'), PHP_URL_PATH);
        }
    }
    foreach ($panel->getPages() as $page) {
        if ($page::canAccess()) {
            $urls[] = parse_url($page::getUrl(), PHP_URL_PATH);
        }
    }

    foreach (array_unique($urls) as $path) {
        $this->actingAs($this->owner)->get($this->base . $path)->assertOk();
    }
    expect(count($urls))->toBeGreaterThan(3);
});

it('hides the admin panel from plain employees', function () {
    $employee = User::create(['name' => 'E', 'email' => 'emp@pest.test', 'password' => 'x']);
    $employee->assignRole(Role::Employee->value);

    $this->actingAs($employee)->get($this->base . '/admin')->assertForbidden();
});

it('stops a receptionist from opening organisation settings', function () {
    $rec = User::create(['name' => 'R', 'email' => 'rec2@pest.test', 'password' => 'x']);
    $rec->assignRole(Role::Receptionist->value);

    $this->actingAs($rec)->get($this->base . '/admin/branches')->assertForbidden();
});

it('links the panel to real, global CSS/JS files (not tenant asset URLs)', function () {
    $html = $this->get($this->base . '/admin/login')->assertOk()->getContent();

    preg_match_all('#(?:href|src)="https?://[^/"]+(/(?:css|js)/filament/[^"?]+)#', $html, $m);
    expect($html)->not->toContain('/tenancy/assets/')
        ->and($m[1])->not->toBeEmpty();
    foreach ($m[1] as $path) {
        expect(file_exists(public_path($path)))->toBeTrue("missing published asset {$path} — run php artisan filament:assets");
    }
});
