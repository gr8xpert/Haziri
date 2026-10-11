<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Request lists (leave, corrections) and the dashboard: users with the final-approval (or view-all) permission see everyone;
 * managers see their direct reports and themselves (spec §8).
 */
trait ScopesToTeam
{
    protected static function scopeToTeam(Builder $query, string $finalPermission): Builder
    {
        $ids = static::teamEmployeeIds($finalPermission);

        return $ids === null ? $query : $query->whereIn('employee_id', $ids);
    }

    /**
     * Employees the signed-in user may see: null = everyone (they hold $permission), else their direct reports and themselves.
     *
     * @return list<int>|null
     */
    protected static function teamEmployeeIds(string $permission): ?array
    {
        $user = auth()->user();
        if ($user === null || $user->can($permission)) {
            return null;
        }

        return Employee::query()->where('manager_employee_id', $user->employee_id ?? 0)->pluck('id')
            ->push($user->employee_id ?? 0)->map(fn ($id) => (int) $id)->all();
    }
}
