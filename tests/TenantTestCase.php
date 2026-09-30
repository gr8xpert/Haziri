<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Tenants\ProvisionTenant;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Feature tests that run inside a tenant. The central test DB and a `pest` tenant (hazri_t_pest) are rebuilt
 * once per test run; each test then runs in a transaction on the tenant connection that is rolled back.
 */
abstract class TenantTestCase extends TestCase
{
    private static bool $booted = false;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$booted) {
            DB::connection('central')->statement('DROP DATABASE IF EXISTS hazri_t_pest');
            Artisan::call('migrate:fresh', ['--force' => true]);
            app(ProvisionTenant::class)('pest', 'Pest Test Co', 'owner@pest.test');
            self::$booted = true;
        }

        $this->tenant = Tenant::query()->where('slug', 'pest')->firstOrFail();
        tenancy()->initialize($this->tenant);
        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('tenant')->rollBack();
        tenancy()->end();

        parent::tearDown();
    }
}
