<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Actions\Holidays\LoadPakistanHolidays;
use App\Enums\Role;
use App\Events\TenantProvisioned;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\DefaultsSeeder;
use Database\Seeders\Tenant\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Spec §4.2: tenant row + domain → DB create + migrate (stancl TenantCreated pipeline)
 * → seed defaults → owner user → TenantProvisioned.
 */
final class ProvisionTenant
{
    public const SLUG_PATTERN = '/^[a-z][a-z0-9-]{1,30}[a-z0-9]$/';

    private const RESERVED = ['admin', 'adms', 'api', 'www', 'mail', 'app', 'static', 'cdn'];

    /**
     * @return array{tenant: Tenant, owner: User, password: string}
     */
    public function __invoke(
        string $slug,
        string $companyName,
        string $adminEmail,
        ?string $planCode = null,
        ?string $adminName = null,
    ): array {
        $slug = Str::lower(trim($slug));
        $this->validate($slug, $adminEmail);

        $plan = $planCode ? Plan::query()->where('code', $planCode)->firstOrFail() : null;

        $tenant = Tenant::create([
            'slug' => $slug,
            'company_name' => $companyName,
            'plan_id' => $plan?->id,
            'employee_limit' => $plan?->limits['employees'] ?? null,
        ]);
        // Subdomain identification: the domain row holds just the subdomain; custom domains hold the full host.
        $tenant->domains()->create(['domain' => $slug]);

        foreach ($plan?->feature_flags ?? [] as $flag => $enabled) {
            TenantSetting::create(['tenant_id' => $tenant->id, 'key' => "feature.{$flag}", 'value' => (bool) $enabled]);
        }

        $password = Str::password(16, symbols: false);
        $owner = $tenant->run(function () use ($tenant, $adminEmail, $adminName, $password, $plan) {
            (new RolesAndPermissionsSeeder)->run();
            (new DefaultsSeeder)->run();
            app(LoadPakistanHolidays::class)((int) CarbonImmutable::now($tenant->timezone)->year);

            foreach ($plan?->feature_flags ?? [] as $flag => $enabled) {
                DB::table('settings')->updateOrInsert(['key' => "feature.{$flag}"], ['value' => json_encode((bool) $enabled)]);
            }

            $owner = User::create([
                'name' => $adminName ?? 'Owner',
                'email' => $adminEmail,
                'password' => $password,
            ]);
            $owner->assignRole(Role::Owner->value);

            return $owner;
        });

        \App\Models\Central\TenantUserLink::link($adminEmail, $tenant->id);
        event(new TenantProvisioned($tenant));

        return ['tenant' => $tenant, 'owner' => $owner, 'password' => $password];
    }

    private function validate(string $slug, string $email): void
    {
        $errors = [];
        if (! preg_match(self::SLUG_PATTERN, $slug) || in_array($slug, self::RESERVED, true)) {
            $errors['slug'] = 'Slug must be 3–32 lowercase letters, digits or dashes, start with a letter, and not be reserved.';
        } elseif (Tenant::query()->where('slug', $slug)->exists()) {
            $errors['slug'] = "Tenant '{$slug}' already exists.";
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'A valid admin email is required.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
