<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Tenant rule parameters and feature flags (spec §7.4). Read through App\Services\Settings\TenantSettings. */
class Setting extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $casts = ['value' => 'json'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'value'])->logOnlyDirty();
    }
}
