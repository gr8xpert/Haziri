<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\Employee;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Branch-limited users (shop managers) see only their branches: employees by branch, everything else through
 * its employee_id. Inactive unless BranchAccess has a restriction for the current request.
 */
final class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ids = BranchAccess::ids();
        if ($ids === null) {
            return;
        }
        if ($model instanceof Employee) {
            $builder->whereIn($model->qualifyColumn('branch_id'), $ids);

            return;
        }
        $builder->whereIn($model->qualifyColumn('employee_id'),
            Employee::query()->withoutGlobalScope(self::class)->withTrashed()->select('id')->whereIn('branch_id', $ids));
    }
}
