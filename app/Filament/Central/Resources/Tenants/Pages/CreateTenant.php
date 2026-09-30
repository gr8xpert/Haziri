<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Actions\Tenants\ProvisionTenant;
use App\Filament\Central\Resources\Tenants\TenantResource;
use App\Models\Central\Plan;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Creation goes through ProvisionTenant (DB + migrations + seed + owner), never a bare insert. */
class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

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

        Notification::make()->success()->persistent()
            ->title('Company created')
            ->body("Owner login: {$result['owner']->email} / {$result['password']} (shown once)")
            ->send();

        return $tenant;
    }
}
