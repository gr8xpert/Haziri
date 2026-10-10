<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Models\Central\Tenant;
use App\Services\Demo\DemoCompanySeeder;
use App\Services\Features\FeatureCatalog;
use App\Support\DemoMode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Fills a company with ~6 weeks of realistic data so every screen has something to show (DemoCompanySeeder).
 * Locally any company; on a live server only the public demo company (HAZRI_DEMO_TENANT).
 */
#[Signature('hazri:demo:seed {slug=demo}')]
#[Description('Fill a company with demo employees, punches, leave, payroll (local, or the public demo company)')]
final class SeedDemoData extends Command
{
    public function handle(DemoCompanySeeder $seeder): int
    {
        $slug = (string) $this->argument('slug');
        if (app()->isProduction() && ! DemoMode::isDemoSlug($slug)) {
            $this->error('On a live server only the demo company (HAZRI_DEMO_TENANT) can be filled with demo data.');

            return self::FAILURE;
        }
        $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
        config(['queue.default' => 'sync']);

        // Every feature except white-label, so every page is on show.
        $flags = ['white_label' => false] + array_fill_keys(FeatureCatalog::fromConfig()->keys(), true);
        app(SetTenantFeatureFlags::class)($tenant, $flags);
        $tenant->update(['employee_limit' => null]);

        $tenant->run(fn () => $seeder->run(fn (string $line) => $this->line($line)));

        $this->info('Demo data ready.');

        return self::SUCCESS;
    }
}
