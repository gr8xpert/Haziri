<?php

declare(strict_types=1);

namespace App\Support\Security;

/**
 * SSRF guard for tenant-configured outbound URLs (SMS gateway, webhooks): http(s) only, no credentials in the URL,
 * and every resolved address must be public (no loopback, private, link-local/metadata, CGNAT or reserved ranges).
 * Check on save AND immediately before each request (DNS can change between the two).
 */
final class PublicUrl
{
    public static function check(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return 'Not a valid URL.';
        }
        if (! in_array(strtolower($parts['scheme']), ['https', 'http'], true)) {
            return 'Only http(s) URLs are allowed.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Credentials in the URL are not allowed.';
        }
        $host = trim($parts['host'], '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if ($ips === []) {
            return 'The host name does not resolve.';
        }
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                return 'The URL points to a private or reserved address.';
            }
        }

        return null;
    }

    public static function isPublic(string $ip): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        if (strlen($bin) === 16) {
            // IPv6 that carries an IPv4 address — judge the embedded IPv4 instead (PHP's range flags miss these):
            // ::ffff:a.b.c.d (mapped), ::a.b.c.d (compatible), 64:ff9b::/96 (NAT64), 2002:aabb:ccdd::/16 (6to4).
            $embedded = match (true) {
                substr($bin, 0, 10) === str_repeat("\0", 10) && in_array(substr($bin, 10, 2), ["\xff\xff", "\0\0"], true) => substr($bin, 12, 4),
                substr($bin, 0, 12) === "\x00\x64\xff\x9b" . str_repeat("\0", 8) => substr($bin, 12, 4),
                substr($bin, 0, 2) === "\x20\x02" => substr($bin, 2, 4),
                default => null,
            };
            if ($embedded !== null) {
                return $bin !== str_repeat("\0", 16) && self::isPublic((string) inet_ntop($embedded));
            }
        }
        $ip = (string) inet_ntop($bin); // canonical form for the checks below
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        // Ranges the PHP flags miss: CGNAT 100.64/10, benchmarking 198.18/15, IPv6 unique-local / link-local.
        foreach (['100.64.0.0/10', '198.18.0.0/15', '0.0.0.0/8'] as $cidr) {
            if (self::inV4Cidr($ip, $cidr)) {
                return false;
            }
        }
        $lower = strtolower($ip);

        return ! (str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd') || str_starts_with($lower, 'fe80') || $lower === '::1');
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
    }

    private static function inV4Cidr(string $ip, string $cidr): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        [$net, $bits] = explode('/', $cidr);
        $mask = -1 << (32 - (int) $bits);

        return (ip2long($ip) & $mask) === (ip2long($net) & $mask);
    }
}
