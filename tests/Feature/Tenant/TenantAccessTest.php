<?php

declare(strict_types=1);

use App\Enums\TenantStatus;

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
