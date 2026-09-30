<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Idempotent: safe to re-run after adding permissions to config/permissions.php. */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = config('permissions.permissions');
        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('permissions.roles') as $roleName => $grants) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($this->expand($grants, $all));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $grants  permission names, '*' for all, '-name' to exclude
     * @param  list<string>  $all
     * @return list<string>
     */
    private function expand(array $grants, array $all): array
    {
        $result = in_array('*', $grants, true) ? $all : array_values(array_filter($grants, fn ($g) => $g[0] !== '-'));
        $excluded = array_map(fn ($g) => substr($g, 1), array_filter($grants, fn ($g) => $g[0] === '-'));

        return array_values(array_diff($result, $excluded));
    }
}
