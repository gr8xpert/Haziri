<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Central\Tenant;

/**
 * The web address of a company: its own custom domain when it has one, else {slug}.{central domain}, with the scheme
 * and port of APP_URL. Used for links made outside a web request (queued jobs, scheduled notifications), which would
 * otherwise point at the platform's own address.
 */
final class TenantUrl
{
    public static function base(Tenant $tenant): string
    {
        $domains = $tenant->domains()->pluck('domain');
        $custom = $domains->first(fn (string $d) => str_contains($d, '.'));
        $host = $custom ?? ($domains->first() ?? $tenant->slug) . '.' . config('hazri.central_domain');

        $app = parse_url((string) config('app.url'));
        $scheme = $app['scheme'] ?? 'https';
        $port = isset($app['port']) && $custom === null ? ':' . $app['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }
}
