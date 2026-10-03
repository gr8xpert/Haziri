<?php

declare(strict_types=1);

use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Filament\Central\Resources\Plans\Pages\ManagePlans;
use App\Filament\Central\Resources\Tenants\Pages\EditTenant;
use App\Filament\Central\Resources\Tenants\Pages\ListTenants;
use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\Setting;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    if (! Schema::hasTable('super_admins')) {
        $this->artisan('migrate', ['--force' => true]);
    }
    Filament::setCurrentPanel(Filament::getPanel('central'));
    $this->actingAs(SuperAdmin::firstOrCreate(['email' => 'sa@hazri.test'], ['name' => 'SA', 'password' => 'password']), 'super_admin');
});

it('switches connected features together while editing a plan', function () {
    $page = Livewire::test(ManagePlans::class)->mountAction('create');

    // Payroll Full parts need Payroll Lite: switching one on brings Lite with it…
    $page->set('mountedActions.0.data.feature_flags.payroll_tax', true)
        ->assertSet('mountedActions.0.data.feature_flags.payroll_lite', true);

    // …and switching Lite off takes the Payroll Full parts with it.
    $page->set('mountedActions.0.data.feature_flags.payroll_lite', false)
        ->assertSet('mountedActions.0.data.feature_flags.payroll_tax', false);
});

it('saves a company\'s feature switches with connected changes on its edit page', function () {
    $tenant = Tenant::where('slug', 'pest')->first() ?? $this->markTestSkipped('needs the pest tenant');
    $original = SetTenantFeatureFlags::current($tenant);
    app(SetTenantFeatureFlags::class)($tenant, ['kiosk' => false, 'kiosk_face' => false]);

    try {
        Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
            ->assertSet('data.flags.kiosk', false)
            ->set('data.flags.kiosk_face', true)
            ->assertSet('data.flags.kiosk', true)
            ->call('save')
            ->assertHasNoFormErrors();

        expect(SetTenantFeatureFlags::current($tenant))->toMatchArray(['kiosk' => true, 'kiosk_face' => true])
            ->and($tenant->fresh()->getAttributes())->not->toHaveKey('flags');
    } finally {
        app(SetTenantFeatureFlags::class)($tenant, $original);
    }
});

it('gives a company the new plan\'s features when its plan is changed', function () {
    $tenant = Tenant::where('slug', 'pest')->first() ?? $this->markTestSkipped('needs the pest tenant');
    $original = ['flags' => SetTenantFeatureFlags::current($tenant), 'plan_id' => $tenant->plan_id];
    $plan = Plan::create(['code' => 'pest-move', 'name' => 'Pest move', 'price_per_employee' => 0,
        'feature_flags' => ['visitors' => true, 'leave' => false]]);
    app(SetTenantFeatureFlags::class)($tenant, ['visitors' => false, 'leave' => true]);

    try {
        Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
            ->set('data.plan_id', (string) $plan->id)
            ->assertSet('data.flags.visitors', true)
            ->assertSet('data.flags.leave', false)
            ->call('save')
            ->assertHasNoFormErrors();

        expect($tenant->fresh()->plan_id)->toBe($plan->id)
            ->and(SetTenantFeatureFlags::current($tenant))->toMatchArray(['visitors' => true, 'leave' => false]);
    } finally {
        $tenant->update(['plan_id' => $original['plan_id']]);
        app(SetTenantFeatureFlags::class)($tenant, $original['flags']);
        $plan->delete();
    }
});

it('saves a plan with every feature switch present', function () {
    Livewire::test(ManagePlans::class)
        ->callAction('create', data: ['code' => 'pest-plan', 'name' => 'Pest plan', 'price_per_employee' => 99, 'price_fixed' => 0,
            'feature_flags' => ['kiosk' => true, 'kiosk_face' => true, 'payroll_lite' => true]])
        ->assertHasNoActionErrors();

    $flags = Plan::where('code', 'pest-plan')->value('feature_flags');
    expect($flags)->toMatchArray(['kiosk' => true, 'kiosk_face' => true, 'payroll_lite' => true])
        ->and(array_keys($flags))->toContain('payroll_tax', 'white_label');

    Plan::where('code', 'pest-plan')->delete();
});

it('applies a plan\'s changed switches to its companies and keeps their own extras', function () {
    $tenant = Tenant::where('slug', 'pest')->first() ?? $this->markTestSkipped('needs the pest tenant');
    $original = ['flags' => SetTenantFeatureFlags::current($tenant), 'plan_id' => $tenant->plan_id];
    $plan = Plan::create(['code' => 'pest-sync', 'name' => 'Pest sync', 'price_per_employee' => 0,
        'feature_flags' => ['visitors' => false, 'leave' => false]]);
    $tenant->update(['plan_id' => $plan->id]);
    app(SetTenantFeatureFlags::class)($tenant, ['visitors' => false, 'leave' => true]); // leave: this company's own extra

    try {
        Livewire::test(ManagePlans::class)
            ->callTableAction('edit', $plan, data: ['feature_flags' => ['visitors' => true, 'leave' => false]])
            ->assertHasNoTableActionErrors();

        expect(SetTenantFeatureFlags::current($tenant))->toMatchArray(['visitors' => true, 'leave' => true]);
    } finally {
        $tenant->update(['plan_id' => $original['plan_id']]);
        app(SetTenantFeatureFlags::class)($tenant, $original['flags']);
        $plan->delete();
    }
});

it('sets a white-label client\'s branding from the super-admin panel', function () {
    $tenant = Tenant::where('slug', 'pest')->first() ?? $this->markTestSkipped('needs the pest tenant');
    $original = SetTenantFeatureFlags::current($tenant);
    app(SetTenantFeatureFlags::class)($tenant, ['white_label' => true]);

    try {
        Livewire::test(ListTenants::class)
            ->mountTableAction('branding', $tenant)
            ->setTableActionData(['app_name' => 'AASML Attendance', 'brand_color' => '#1d4ed8', 'branding_locked' => true])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $saved = $tenant->run(fn () => Setting::whereIn('key', ['app_name', 'brand_color', 'branding_locked'])->pluck('value', 'key')->all());
        expect($saved)->toMatchArray(['app_name' => 'AASML Attendance', 'brand_color' => '#1d4ed8', 'branding_locked' => true]);
    } finally {
        $tenant->run(fn () => Setting::whereIn('key', ['app_name', 'brand_color', 'branding_locked'])->delete());
        app(SetTenantFeatureFlags::class)($tenant, $original);
    }
});
