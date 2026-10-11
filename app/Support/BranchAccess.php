<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\Settings\TenantSettings;

/**
 * Which branches the signed-in admin-panel user may see (feature `multi_branch`). Set per request by
 * ScopeToUserBranches; read by BranchScope. null = everything. Queued jobs run unrestricted (suspend/resume).
 */
final class BranchAccess
{
    /** @var list<int>|null */
    private static ?array $ids = null;

    private static int $suspended = 0;

    /**
     * Branch ids $user is limited to, or null when they see every branch: owners/admins, users without branches,
     * or the feature switched off.
     *
     * @return list<int>|null
     */
    public static function forUser(?User $user): ?array
    {
        if ($user === null || ! app(TenantSettings::class)->featureEnabled('multi_branch')
            || $user->hasAnyRole([Role::Owner->value, Role::Admin->value])) {
            return null;
        }
        $ids = $user->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();

        return $ids === [] ? null : $ids;
    }

    /** @param list<int>|null $ids */
    public static function restrictTo(?array $ids): void
    {
        self::$ids = $ids;
    }

    /** @return list<int>|null */
    public static function ids(): ?array
    {
        return self::$suspended > 0 ? null : self::$ids;
    }

    public static function restricted(): bool
    {
        return self::ids() !== null;
    }

    public static function suspend(): void
    {
        self::$suspended++;
    }

    public static function resume(): void
    {
        self::$suspended = max(0, self::$suspended - 1);
    }

    public static function clear(): void
    {
        self::$ids = null;
        self::$suspended = 0;
    }

    /**
     * Branches to offer in a filter: only the user's own when branch-limited.
     *
     * @return array<int, string>
     */
    public static function branchOptions(): array
    {
        $ids = self::ids();

        return Branch::query()->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Departments to offer in a filter: for a branch-limited user, only those with staff in their branches.
     *
     * @return array<int, string>
     */
    public static function departmentOptions(): array
    {
        return Department::query()
            ->when(self::restricted(), fn ($q) => $q->whereIn('id', Employee::query()->whereNotNull('department_id')->select('department_id')))
            ->orderBy('name')->pluck('name', 'id')->all();
    }
}
