<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Filament\Central\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\EditRecord;

/** Company details plus its feature switches, which live in tenant settings rather than on the tenant row. */
class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /** @var array<string, bool> */
    private array $flags = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + ['flags' => SetTenantFeatureFlags::current($this->getRecord())];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->flags = array_map('boolval', $data['flags'] ?? []);
        unset($data['flags']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(SetTenantFeatureFlags::class)($this->getRecord(), $this->flags);
    }
}
