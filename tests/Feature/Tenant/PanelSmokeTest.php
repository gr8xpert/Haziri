<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Filament\Tenant\Widgets\InOfficeNow;
use App\Filament\Tenant\Widgets\TodayStats;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\Features\FeatureCatalog;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

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
        $response = $this->actingAs($this->owner)->get($this->base . $path);
        // Reports merged into another one only redirect there (old links keep working); everything else renders.
        $response->isRedirect() ? expect($response->headers->get('Location'))->toContain('/admin/reports/') : $response->assertOk();
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

it('gives every menu item its own icon', function () {
    $panel = Filament::getPanel('tenant');
    $icons = collect([...$panel->getResources(), ...$panel->getPages()])
        ->filter(fn (string $class) => $class::shouldRegisterNavigation())
        ->mapWithKeys(fn (string $class) => [class_basename($class) => $class::getNavigationIcon()])
        ->filter()
        ->map(fn ($icon) => $icon instanceof BackedEnum ? $icon->value : (string) $icon);

    expect($icons->count())->toBeGreaterThan(20)->and($icons->duplicates()->all())->toBe([]);
});

it('gives a manager a dashboard for their own team and counts exempted staff as off', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-01 12:00', 'Asia/Karachi'));
    $boss = Employee::create(['code' => 'DB0', 'first_name' => 'Bossa', 'joined_on' => '2025-01-01']);
    $mine = Employee::create(['code' => 'DB1', 'first_name' => 'Teamina', 'joined_on' => '2025-01-01', 'manager_employee_id' => $boss->id]);
    $excused = Employee::create(['code' => 'DB2', 'first_name' => 'Excusa', 'joined_on' => '2025-01-01', 'manager_employee_id' => $boss->id]);
    $other = Employee::create(['code' => 'DB3', 'first_name' => 'Outsidera', 'joined_on' => '2025-01-01']);
    foreach ([[$boss, AttendanceStatus::Present], [$mine, AttendanceStatus::Present], [$excused, AttendanceStatus::Exempted], [$other, AttendanceStatus::Present]] as [$e, $status]) {
        AttendanceDay::create(['employee_id' => $e->id, 'date' => '2026-09-01', 'status' => $status, 'flags' => ['missing_out'], 'first_in' => '2026-09-01 04:00:00']);
    }
    $manager = User::create(['name' => 'Bossa', 'email' => 'boss-dash@pest.test', 'password' => 'secret123', 'employee_id' => $boss->id]);
    $manager->assignRole(Role::Manager->value);
    $this->actingAs($manager);

    expect(TodayStats::canView())->toBeTrue()->and(InOfficeNow::canView())->toBeTrue();
    Livewire::test(TodayStats::class)->assertSee('of 3 in your team')->assertSee('Everyone accounted for')->assertDontSee('Unmapped PINs');
    Livewire::test(InOfficeNow::class)->assertSee('Teamina')->assertDontSee('Outsidera');
});
