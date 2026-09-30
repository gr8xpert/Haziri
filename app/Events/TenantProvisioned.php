<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

final class TenantProvisioned
{
    use Dispatchable;

    public function __construct(public readonly Tenant $tenant) {}
}
