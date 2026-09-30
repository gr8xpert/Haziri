<?php

declare(strict_types=1);

use App\Models\Central\SuperAdmin;

beforeEach(function () {
    // The tenant test case builds the schema once; make sure central tables exist when this file runs alone.
    if (! Illuminate\Support\Facades\Schema::hasTable('super_admins')) {
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
