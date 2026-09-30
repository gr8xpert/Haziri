<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;

/** Who gets what (spec §11). */
final class Recipients
{
    /** @return Collection<int, Recipient> */
    public static function withRoles(Role ...$roles): Collection
    {
        return User::role(array_map(fn (Role $r) => $r->value, $roles))->where('is_active', true)->get()
            ->map(fn (User $u) => Recipient::fromUser($u));
    }

    /** @return Collection<int, Recipient> */
    public static function withPermission(string $permission): Collection
    {
        return User::permission($permission)->where('is_active', true)->get()->map(fn (User $u) => Recipient::fromUser($u));
    }

    /** @return Collection<int, Recipient> */
    public static function managerOf(Employee $employee): Collection
    {
        if (! $employee->manager_employee_id) {
            return collect();
        }
        $user = User::query()->where('employee_id', $employee->manager_employee_id)->where('is_active', true)->first();

        return $user ? collect([Recipient::fromUser($user)]) : collect();
    }

    /**
     * People who must act on the current step of an approval chain.
     *
     * @param  list<array<string, mixed>>  $chain
     * @return Collection<int, Recipient>
     */
    public static function approversFor(array $chain, int $current, string $finalPermission): Collection
    {
        $step = $chain[$current] ?? null;
        if ($step === null) {
            return collect();
        }
        if ($step['step'] === 'manager' && $step['user_id']) {
            $user = User::find($step['user_id']);

            return $user ? collect([Recipient::fromUser($user)]) : collect();
        }

        return self::withPermission($finalPermission);
    }
}
