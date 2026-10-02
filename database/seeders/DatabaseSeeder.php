<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tenants\ProvisionTenant;
use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Services\Features\FeatureCatalog;
use Illuminate\Database\Seeder;

/** Central DB seed: plans (spec §16 pricing defaults), a local super-admin, and a demo tenant. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = FeatureCatalog::fromConfig();
        $allFlags = array_fill_keys($catalog->keys(), true);
        $payrollFull = array_fill_keys(array_keys($catalog->inGroup('payroll_full')), false);

        $plans = [
            'starter' => ['Starter', 150, ['employees' => 25], array_merge($allFlags, $payrollFull, ['payroll_lite' => false, 'ai_reports' => false,
                'whatsapp' => false, 'multi_branch' => false, 'kiosk_face' => false, 'public_api' => false, 'white_label' => false])],
            'business' => ['Business', 120, ['employees' => 100], array_merge($allFlags, $payrollFull, ['ai_reports' => false, 'kiosk_face' => false])],
            'enterprise' => ['Enterprise', 100, ['employees' => null], $allFlags],
        ];
        foreach ($plans as $code => [$name, $price, $limits, $flags]) {
            Plan::updateOrCreate(['code' => $code], [
                'name' => $name,
                'price_per_employee' => $price,
                'limits' => $limits,
                'feature_flags' => $catalog->normalise($flags),
            ]);
        }

        if (app()->isLocal()) {
            SuperAdmin::firstOrCreate(
                ['email' => 'admin@hazri.localhost'],
                ['name' => 'Super Admin', 'password' => 'password'],
            );

            if (! Tenant::query()->where('slug', 'demo')->exists()) {
                $result = app(ProvisionTenant::class)('demo', 'Demo Traders (Pvt) Ltd', 'owner@demo.test', 'business', 'Demo Owner');
                // Tenant models must be saved inside tenant context (they use the default connection).
                $result['tenant']->run(fn () => $result['owner']->forceFill(['password' => 'password'])->save());
                $this->command?->info('Demo tenant: http://demo.' . config('hazri.central_domain') . ':8000/admin — owner@demo.test / password');
            }
        }
    }
}
