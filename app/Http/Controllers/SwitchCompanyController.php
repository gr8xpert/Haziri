<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantUserLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Multi-company switch (spec §9 phase 4): the signed-in user's email must be linked to the target company
 * and exist there as an active user; a single-use 60 s login token hands the session over.
 */
final class SwitchCompanyController
{
    public function __invoke(Request $request, string $tenantId): RedirectResponse
    {
        $email = strtolower((string) $request->user()->email);
        abort_unless(TenantUserLink::query()->where(['email' => $email, 'tenant_id' => $tenantId])->exists(), 403);

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

    /** @return array<string, string> tenant id => company name, excluding the current one */
    public static function optionsFor(?string $email): array
    {
        if ($email === null) {
            return [];
        }

        return TenantUserLink::query()->with('tenant')->where('email', strtolower($email))->where('tenant_id', '!=', tenant('id'))->get()
            ->filter(fn (TenantUserLink $l) => $l->tenant !== null)
            ->mapWithKeys(fn (TenantUserLink $l) => [$l->tenant_id => $l->tenant->company_name])->all();
    }
}
