<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** /me routes: a logged-in, active user linked to an employee record. */
final class EnsureEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->is_active || $user->employee_id === null || $user->employee === null) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'unauthenticated'], 401);
            }

            return redirect()->route('me.login');
        }
        app()->setLocale($user->locale ?: 'en');

        return $next($request);
    }
}
