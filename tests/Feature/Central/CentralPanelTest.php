<?php

declare(strict_types=1);

use App\Filament\Central\Widgets\PlatformStats;
use App\Filament\Central\Widgets\RecentCompanies;
use App\Models\Central\Invoice;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // The tenant test case builds the schema once; make sure central tables exist when this file runs alone.
    if (! Schema::hasTable('super_admins')) {
        $this->artisan('migrate', ['--force' => true]);
    }
    $this->admin = SuperAdmin::firstOrCreate(['email' => 'sa@hazri.test'], ['name' => 'SA', 'password' => 'password']);
    $this->base = 'http://' . config('hazri.admin_domain');
});

it('renders the central panel pages for a super admin', function () {
    foreach (['/', '/tenants', '/tenants/create', '/plans', '/invoices', '/unknown-devices'] as $path) {
        $this->actingAs($this->admin, 'super_admin')->get($this->base . $path)->assertOk();
    }
});

it('redirects guests to the super admin login', function () {
    $this->get($this->base . '/tenants')->assertRedirect();
});

it('does not serve the central panel on a tenant subdomain', function () {
    $this->actingAs($this->admin, 'super_admin')->get('http://pest.' . config('hazri.central_domain') . '/tenants')->assertNotFound();
});

it('shows the platform overview on the super-admin dashboard, with overdue invoices called out', function () {
    $tenant = Tenant::where('slug', 'pest')->first() ?? $this->markTestSkipped('needs the pest tenant');
    $invoice = Invoice::create(['tenant_id' => $tenant->id, 'number' => 'HZ-PEST-OVERDUE', 'period_start' => now()->subMonth()->startOfMonth(),
        'period_end' => now()->subMonth()->endOfMonth(), 'employee_count' => 3, 'amount' => 450, 'lines' => [], 'due_on' => now()->subDay()]);
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('central'));
    $this->actingAs($this->admin, 'super_admin');

    try {
        Livewire\Livewire::test(PlatformStats::class)
            ->assertSee(['Companies', 'Active employees', 'Unpaid', 'Payments to confirm', 'Unknown machines'])
            ->assertSee('overdue');
        Livewire\Livewire::test(RecentCompanies::class)->assertSee($tenant->company_name);
        $this->get($this->base . '/')->assertOk()->assertSeeLivewire(PlatformStats::class)->assertSeeLivewire(RecentCompanies::class);
    } finally {
        $invoice->delete();
    }
});
