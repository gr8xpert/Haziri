<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\BranchAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin panel: limit a branch manager's queries to their branches for the length of this request. */
final class ScopeToUserBranches
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        BranchAccess::restrictTo(BranchAccess::forUser($user instanceof User ? $user : null));

        try {
            return $next($request);
        } finally {
            BranchAccess::clear();
        }
    }
}
