<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\Tenant\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Re-apply config/permissions.php to the current tenant (deploy step, via tenants:run). Custom roles are untouched. */
#[Signature('hazri:permissions:sync')]
#[Description('Sync seeded roles & permissions from config/permissions.php')]
final class SyncPermissions extends Command
{
    public function handle(): int
    {
        (new RolesAndPermissionsSeeder)->run();
        $this->info('Permissions synced.');

        return self::SUCCESS;
    }
}
