<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Models\User;

it('blocks a suspended tenant', function () {
    $this->tenant->update(['status' => TenantStatus::Suspended]);
    try {
        $this->get('http://pest.' . config('hazri.central_domain') . '/admin/login')->assertForbidden();
    } finally {
        $this->tenant->update(['status' => TenantStatus::Trial]);
    }
});

it('serves the single tenant on any host in dedicated mode', function () {
    config(['hazri.mode' => 'dedicated', 'hazri.dedicated_tenant' => 'pest', 'tenancy.central_domains' => ['admin.client.test']]);

    $this->get('http://attendance.client.test/admin/login')->assertOk();
});

it('returns 404 for an unknown subdomain', function () {
    tenancy()->end();
    $this->get('http://nobody.' . config('hazri.central_domain') . '/admin/login')->assertNotFound();
    tenancy()->initialize($this->tenant);
});

it('lets a company suspended for non-payment sign in and open Billing, and nothing else', function () {
    $this->tenant->update(['status' => TenantStatus::Suspended, 'suspension_reason' => 'billing']);
    $base = 'http://pest.' . config('hazri.central_domain');
    $owner = User::where('email', 'owner@pest.test')->firstOrFail();
    try {
        $this->get("{$base}/admin/login")->assertOk();
        $this->actingAs($owner)->get("{$base}/admin/billing")->assertOk();
        $this->actingAs($owner)->get("{$base}/admin/employees")->assertRedirect('/admin/billing');
        $this->actingAs($owner)->get("{$base}/me")->assertForbidden();

        $this->tenant->update(['suspension_reason' => null]);
        $this->actingAs($owner)->get("{$base}/admin/billing")->assertForbidden();
    } finally {
        $this->tenant->update(['status' => TenantStatus::Trial, 'suspension_reason' => null]);
    }
});
