<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant identification for every tenant route and the tenant panel.
 * shared mode:    {slug}.{central domain} or a custom domain (stancl DomainOrSubdomain).
 * dedicated mode: every non-central host is the single HAZRI_DEDICATED_TENANT (spec §3.2).
 * Suspended tenants, and trials past their end date, get a 403 page; a company suspended for non-payment can still
 * sign in and open Billing to report the payment.
 */
final class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('hazri.mode') === 'dedicated') {
            $tenant = Tenant::query()->where('slug', config('hazri.dedicated_tenant'))->first();
            abort_if($tenant === null, 503, 'HAZRI_DEDICATED_TENANT is not provisioned.');
            if (! tenancy()->initialized || tenant('id') !== $tenant->id) {
                tenancy()->initialize($tenant);
            }

            return $this->guard($tenant, $request, $next);
        }

        return app(InitializeTenancyByDomainOrSubdomain::class)->handle(
            $request,
            fn (Request $r) => $this->guard(tenancy()->tenant, $r, $next),
        );
    }

    private function guard(?Tenant $tenant, Request $request, Closure $next): Response
    {
        // Checked live so access stops when the trial ends, not at the next daily hazri:billing --enforce run.
        $trialOver = config('hazri.mode') !== 'dedicated'
            && $tenant?->status === TenantStatus::Trial && $tenant->trial_ends_at?->isPast();
        if ($trialOver || ($tenant?->status === TenantStatus::Suspended && $tenant->suspension_reason === 'trial_ended')) {
            abort(403, __('Your free trial has ended. Please contact support to continue.'));
        }
        if ($tenant?->status === TenantStatus::Suspended) {
            if ($tenant->suspension_reason === 'billing') {
                if ($this->isBillingRequest($request)) {
                    return $next($request);
                }
                if ($request->isMethod('GET') && $request->is('admin', 'admin/*') && ! Livewire::isLivewireRequest()) {
                    return redirect('/admin/billing'); // the owner can still report a payment
                }
            }
            abort(403, __('This account is suspended. Please contact support.'));
        }

        return $next($request);
    }

    /**
     * Suspended for an unpaid invoice: only signing in and out and the Billing page stay open, so the owner can report
     * the payment. Livewire calls are checked too: this middleware is persistent, so Livewire re-runs it against the
     * (signed) page path each component was rendered on.
     */
    private function isBillingRequest(Request $request): bool
    {
        return $request->is('admin/login', 'admin/logout', 'admin/billing');
    }
}
