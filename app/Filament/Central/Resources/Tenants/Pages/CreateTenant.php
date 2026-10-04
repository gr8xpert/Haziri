<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Actions\Tenants\ProvisionTenant;
use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Filament\Central\Resources\Tenants\TenantResource;
use App\Models\Central\Plan;
use App\Services\Features\FeatureCatalog;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Creation goes through ProvisionTenant (DB + migrations + seed + owner), never a bare insert. */
class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /** Start with the catalog's default switches; picking a plan replaces them with the plan's. */
    protected function afterFill(): void
    {
        $catalog = FeatureCatalog::fromConfig();
        $this->data['flags'] = array_combine($catalog->keys(), array_map(fn (string $f) => $catalog->default($f), $catalog->keys()));
    }

    protected function handleRecordCreation(array $data): Model
    {
        $raw = $this->form->getRawState();
        $result = app(ProvisionTenant::class)(
            slug: $data['slug'],
            companyName: $data['company_name'],
            adminEmail: $raw['admin_email'],
            planCode: isset($data['plan_id']) ? Plan::find($data['plan_id'])?->code : null,
            adminName: $raw['admin_name'] ?? null,
        );
        $tenant = $result['tenant'];
        $tenant->update(array_filter([
            'timezone' => $data['timezone'] ?? null,
            'employee_limit' => $data['employee_limit'] ?? null,
        ]));
        // The switches as adjusted on the form (the plan's, plus or minus what was changed here).
        if (! empty($data['flags'])) {
            app(SetTenantFeatureFlags::class)($tenant, array_map('boolval', $data['flags']));
        }

        Notification::make()->success()->persistent()
            ->title('Company created')
            ->body("Owner login: {$result['owner']->email} / {$result['password']} (shown once)")
            ->send();

        return $tenant;
    }
}
