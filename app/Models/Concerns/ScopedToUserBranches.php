<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;

/** Records of employees: a branch-limited user only sees those of their branches (see BranchScope). */
trait ScopedToUserBranches
{
    protected static function bootScopedToUserBranches(): void
    {
        static::addGlobalScope(new BranchScope);
    }
}
