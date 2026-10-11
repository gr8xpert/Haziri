<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSwitchLink;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds another company to "Switch company" for the signed-in login. The person proves they own the login with the
 * same email in that company by typing its password, so nobody can reach a company just by creating a login with
 * someone else's email. Only this exact pair of companies is linked, both ways (spec §9 phase 4).
 */
final class LinkCompanyLogin
{
    public function __invoke(User $user, string $address, string $password): Tenant
    {
        $key = 'link-company:' . tenant('id') . ':' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['address' => __('Too many tries. Wait a minute and try again.')]);
        }
        RateLimiter::hit($key, 60);

        $target = $this->find($address);
        if ($target === null || $target->id === tenant('id') || $target->status === TenantStatus::Suspended) {
            throw ValidationException::withMessages(['address' => __('No other company found at that address.')]);
        }

        $email = strtolower((string) $user->email);
        $hash = $target->run(fn () => User::query()->where('email', $email)->where('is_active', true)->value('password'));
        if ($hash === null || ! Hash::check($password, (string) $hash)) {
            throw ValidationException::withMessages(['password' => __('There is no active login with your email and this password in that company.')]);
        }

        RateLimiter::clear($key);
        TenantSwitchLink::pair($email, (string) tenant('id'), $target->id);

        return $target;
    }

    /** Accepts the company's sub-address ("acme"), its full address ("acme.example.com") or its custom domain. */
    private function find(string $address): ?Tenant
    {
        $host = Str::lower(trim((string) (parse_url(str_contains($address, '://') ? $address : "https://{$address}", PHP_URL_HOST) ?? '')));
        if ($host === '') {
            return null;
        }
        $central = '.' . config('hazri.central_domain');
        $domain = str_ends_with($host, $central) ? substr($host, 0, -strlen($central)) : $host;

        return Tenant::query()->whereHas('domains', fn ($q) => $q->where('domain', $domain))->first();
    }
}
