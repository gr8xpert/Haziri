<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSwitchLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Multi-company switch (spec §9 phase 4): there must be a switch pair for the signed-in email from this company to the
 * target, and an active user with that email there; a single-use 60 s login token hands the session over.
 * Pairs are made at provisioning (owner) or by the person proving the target login's password (LinkCompanyLogin).
 */
final class SwitchCompanyController
{
    public function __invoke(Request $request, string $tenantId): RedirectResponse
    {
        $email = strtolower((string) $request->user()->email);
        abort_unless(TenantSwitchLink::allows($email, (string) tenant('id'), $tenantId), 403);

        $target = Tenant::query()->findOrFail($tenantId);
        abort_if($target->status === TenantStatus::Suspended, 403, __('That company is suspended.'));

        $userId = $target->run(fn () => User::query()->where('email', $email)->where('is_active', true)->value('id'));
        abort_if($userId === null, 403, __('You do not have a login in that company.'));

        $token = tenancy()->impersonate($target, (string) $userId, '/admin', 'web');
        $domain = $target->domains()->value('domain');
        $host = str_contains((string) $domain, '.') ? $domain : $domain . '.' . config('hazri.central_domain');
        $port = in_array($request->getPort(), [80, 443], true) ? '' : ':' . $request->getPort();

        return redirect()->away($request->getScheme() . "://{$host}{$port}/impersonate/{$token->token}");
    }

    /** @return array<string, string> tenant id => company name: only companies paired with $fromTenantId (default: this one) */
    public static function optionsFor(?string $email, ?string $fromTenantId = null): array
    {
        $fromTenantId ??= (string) tenant('id');
        if ($email === null || $fromTenantId === '') {
            return [];
        }

        return TenantSwitchLink::query()->with('target')->where(['email' => strtolower($email), 'tenant_id' => $fromTenantId])->get()
            ->filter(fn (TenantSwitchLink $l) => $l->target !== null)
            ->mapWithKeys(fn (TenantSwitchLink $l) => [$l->target_tenant_id => $l->target->company_name])->all();
    }
}
