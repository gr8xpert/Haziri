<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Request lists (leave, corrections): users with the final-approval permission see everyone;
 * managers see their direct reports and themselves (spec §8).
 */
trait ScopesToTeam
{
    protected static function scopeToTeam(Builder $query, string $finalPermission): Builder
    {
        $user = auth()->user();
        if ($user === null || $user->can($finalPermission)) {
            return $query;
        }
        $ids = Employee::query()->where('manager_employee_id', $user->employee_id ?? 0)->pluck('id')
            ->push($user->employee_id ?? 0)->all();

        return $query->whereIn('employee_id', $ids);
    }
}
