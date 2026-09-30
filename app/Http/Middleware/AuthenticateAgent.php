<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Central\AgentKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bridge-agent auth (spec §6.2): `Authorization: Bearer <agent key>` → tenant → tenancy initialised. */
final class AuthenticateAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        $key = $plain !== '' ? AgentKey::findActive($plain) : null;
        if ($key === null) {
            return response()->json(['error' => 'invalid_agent_key'], 401);
        }

        $tenant = $key->tenant;
        if ($tenant === null || $tenant->status === TenantStatus::Suspended) {
            return response()->json(['error' => 'account_suspended'], 403);
        }

        $key->forceFill(['last_used_at' => now()])->saveQuietly();
        tenancy()->initialize($tenant);
        $request->attributes->set('agent_key', $key);

        return $next($request);
    }
}
