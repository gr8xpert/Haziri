<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A company. Lives in the central DB; its data lives in `hazri_t_{slug}`.
 *
 * @property string $id
 * @property string $slug
 * @property string $company_name
 * @property string $timezone
 * @property TenantStatus $status
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $casts = [
        'status' => TenantStatus::class,
        'trial_ends_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /** Real columns; everything else goes into the `data` JSON column. */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'slug', 'company_name', 'timezone', 'status', 'plan_id', 'employee_limit', 'trial_ends_at', 'suspended_at',
            'created_at', 'updated_at',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tenant) {
            $tenant->status ??= TenantStatus::Trial;
            if ($tenant->status === TenantStatus::Trial) {
                $tenant->trial_ends_at ??= now()->addDays((int) config('hazri.billing.trial_days', 15));
            }
            $tenant->timezone ??= config('hazri.default_timezone');
            if (! $tenant->getInternal('db_name')) {
                $tenant->setInternal('db_name', config('hazri.tenant_db_prefix') . str_replace('-', '_', $tenant->slug));
            }
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
