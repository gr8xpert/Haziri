<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The public demo company (config hazri.demo.tenant): anyone can log in with the logins shown on its login page,
 * so it never sends real messages, its logins can't be changed, and it is rebuilt every night.
 */
final class DemoMode
{
    /** Is the current tenant the public demo company? */
    public static function active(): bool
    {
        return tenancy()->initialized && self::isDemoSlug((string) tenant('slug'));
    }

    public static function isDemoSlug(string $slug): bool
    {
        $demo = config('hazri.demo.tenant');

        return filled($demo) && $slug === $demo;
    }

    /** The demo logins, by role, as shown on the login page: {slug}.test addresses, never real inboxes. */
    public static function email(string $slug, string $who): string
    {
        return "{$who}@{$slug}.test";
    }
}
