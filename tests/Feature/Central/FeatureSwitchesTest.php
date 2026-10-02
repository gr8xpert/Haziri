<?php

declare(strict_types=1);

use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Filament\Central\Resources\Plans\Pages\ManagePlans;
use App\Filament\Central\Resources\Tenants\Pages\ListTenants;
use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
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

it('opens a company\'s feature switches and saves them with connected changes', function () {
    $tenant = Tenant::where('slug', 'pest')->first() ?? $this->markTestSkipped('needs the pest tenant');
    $original = SetTenantFeatureFlags::current($tenant);

    try {
        Livewire::test(ListTenants::class)
            ->mountTableAction('features', $tenant)
            ->set('mountedActions.0.data.flags.kiosk_face', true)
            ->assertSet('mountedActions.0.data.flags.kiosk', true)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        expect(SetTenantFeatureFlags::current($tenant))->toMatchArray(['kiosk' => true, 'kiosk_face' => true]);
    } finally {
        app(SetTenantFeatureFlags::class)($tenant, $original);
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
