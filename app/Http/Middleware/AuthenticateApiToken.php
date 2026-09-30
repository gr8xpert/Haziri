<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\Settings\TenantSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Public API auth (spec §13.3): runs after IdentifyTenant; `Authorization: Bearer hzp_…` + ability. */
final class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string $ability = 'read'): Response
    {
        if (! app(TenantSettings::class)->featureEnabled('public_api')) {
            return response()->json(['error' => 'api_not_enabled'], 403);
        }
        $plain = (string) $request->bearerToken();
        $token = $plain !== '' ? ApiToken::query()->where('token_hash', hash('sha256', $plain))->whereNull('revoked_at')->first() : null;
        if ($token === null) {
            return response()->json(['error' => 'invalid_token'], 401);
        }
        if (! $token->can($ability)) {
            return response()->json(['error' => 'missing_ability', 'ability' => $ability], 403);
        }
        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
