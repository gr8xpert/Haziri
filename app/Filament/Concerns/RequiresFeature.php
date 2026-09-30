<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Services\Settings\TenantSettings;

/** Hide a resource/page unless the tenant's plan enables the module (spec §3.3). Set static $feature. */
trait RequiresFeature
{
    public static function canAccess(array $parameters = []): bool
    {
        return app(TenantSettings::class)->featureEnabled(static::$feature) && static::canViewAny();
    }
}
