<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Support\Str;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

/** Tenant ids are ULIDs (spec §4.1). */
final class UlidGenerator implements UniqueIdentifierGenerator
{
    public static function generate($resource): string
    {
        return Str::lower((string) Str::ulid());
    }
}
