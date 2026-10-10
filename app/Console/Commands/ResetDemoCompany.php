<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\ProvisionTenant;
use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Support\DemoMode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Rebuilds the public demo company (config hazri.demo.tenant) from scratch: deletes it with its database and files,
 * provisions it again and fills it with demo data. Runs nightly, so whatever visitors change is gone by morning.
 * Only ever touches that one company.
 */
#[Signature('hazri:demo:reset')]
#[Description('Delete and rebuild the public demo company with fresh demo data (nightly)')]
final class ResetDemoCompany extends Command
{
    public function handle(ProvisionTenant $provision): int
    {
        $slug = (string) config('hazri.demo.tenant');
        if (! DemoMode::isDemoSlug($slug)) {
            $this->error('No demo company configured (HAZRI_DEMO_TENANT).');

            return self::FAILURE;
        }

        $old = Tenant::query()->where('slug', $slug)->first();
        if ($old !== null) {
            $files = $old->run(fn () => storage_path());
            $old->delete(); // drops its database (TenantDeleted pipeline); central rows cascade
            File::deleteDirectory($files);
        }

        $tenant = $provision($slug, (string) config('hazri.demo.company'), DemoMode::email($slug, 'owner'), adminName: 'Kashif Owner')['tenant'];
        $tenant->update(['status' => TenantStatus::Active, 'trial_ends_at' => null]); // never billed or suspended

        return $this->call('hazri:demo:seed', ['slug' => $slug]);
    }
}
