<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\GrantAiReadonly;
use App\Models\Central\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hazri:ai:grant-readonly')]
#[Description('Grant the AI read-only DB user SELECT on every tenant\'s curated views (deploy step)')]
final class GrantAiReadonlyCommand extends Command
{
    public function handle(GrantAiReadonly $grant): int
    {
        $n = 0;
        foreach (Tenant::all() as $tenant) {
            $n += $grant($tenant) ? 1 : 0;
        }
        $this->info($n ? "Granted on {$n} tenant database(s)." : 'AI_DB_READONLY_USERNAME is not set — nothing to do.');

        return self::SUCCESS;
    }
}
