<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Central\Tenant;
use App\Services\Ai\SqlGuard;
use Illuminate\Support\Facades\DB;

/**
 * Spec §14.4: the AI read-only DB user may SELECT the four curated views of a tenant DB — nothing else.
 * Views run with definer rights, so no table-level grant is needed. No-op when AI_DB_READONLY_USERNAME is unset.
 */
final class GrantAiReadonly
{
    public function __invoke(Tenant $tenant): bool
    {
        $user = (string) config('hazri.ai.readonly_db_username');
        if ($user === '' || ! preg_match('/^[A-Za-z0-9_]{1,32}$/', $user)) {
            return false;
        }
        $db = $tenant->database()->getName();
        if (! preg_match('/^[A-Za-z0-9_]+$/', $db)) {
            return false;
        }
        foreach (SqlGuard::VIEWS as $view) {
            DB::connection('central')->statement("GRANT SELECT ON `{$db}`.`{$view}` TO '{$user}'@'%'");
        }

        return true;
    }
}
