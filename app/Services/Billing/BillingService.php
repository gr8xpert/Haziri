<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\EmployeeStatus;
use App\Enums\TenantStatus;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Billing (spec §9 phase 4): monthly invoice = active employees × plan price per employee + fixed price;
 * manual payment confirmation; suspension after due date + grace days; reactivation on payment;
 * employee-count enforcement. No payment gateway in v1 (spec §4.1).
 */
final class BillingService
{
    /** Invoices for the month starting $monthStart, for every billable tenant. Idempotent (one per tenant+month). */
    public function invoiceMonth(CarbonImmutable $monthStart): int
    {
        if (config('hazri.mode') === 'dedicated') {
            return 0;
        }
        $n = 0;
        foreach (Tenant::query()->with('plan')->where('status', '!=', TenantStatus::Trial)->get() as $tenant) {
            if ($tenant->plan === null
                || Invoice::query()->where(['tenant_id' => $tenant->id])->whereDate('period_start', $monthStart)->exists()) {
                continue;
            }
            $count = (int) $tenant->run(fn () => Employee::query()->where('status', '!=', EmployeeStatus::Left)->count());
            $lines = array_values(array_filter([
                ['label' => "{$count} active employees × Rs {$tenant->plan->price_per_employee}", 'amount' => $count * (int) $tenant->plan->price_per_employee],
                $tenant->plan->price_fixed ? ['label' => "{$tenant->plan->name} plan fee", 'amount' => (int) $tenant->plan->price_fixed] : null,
            ]));
            Invoice::create([
                'tenant_id' => $tenant->id,
                'number' => 'HZ-' . $monthStart->format('Ym') . '-' . str_pad((string) (Invoice::query()->count() + 1), 4, '0', STR_PAD_LEFT),
                'period_start' => $monthStart->format('Y-m-d'),
                'period_end' => $monthStart->endOfMonth()->format('Y-m-d'),
                'employee_count' => $count,
                'amount' => array_sum(array_column($lines, 'amount')),
                'lines' => $lines,
                'due_on' => $monthStart->addDays((int) config('hazri.billing.due_days', 10))->format('Y-m-d'),
            ]);
            $n++;
        }

        return $n;
    }

    /** Suspend tenants with an open invoice past due date + grace days (spec: "suspension on non-payment with grace period"). */
    public function enforce(CarbonImmutable $today): int
    {
        $cutoff = $today->subDays((int) config('hazri.billing.grace_days', 15))->format('Y-m-d');
        $overdue = Invoice::query()->where('status', 'open')->where('due_on', '<', $cutoff)->pluck('tenant_id')->unique();
        $n = 0;
        foreach (Tenant::query()->whereIn('id', $overdue)->where('status', TenantStatus::Active)->get() as $tenant) {
            $tenant->status = TenantStatus::Suspended;
            $tenant->suspended_at = now();
            $tenant->suspension_reason = 'billing';
            $tenant->save();
            $n++;
        }

        return $n;
    }

    /** Suspend companies whose trial has run out; the super-admin activates them once they sign up. */
    public function endTrials(CarbonImmutable $now): int
    {
        if (config('hazri.mode') === 'dedicated') {
            return 0;
        }
        $n = 0;
        foreach (Tenant::query()->where('status', TenantStatus::Trial)->where('trial_ends_at', '<=', $now)->get() as $tenant) {
            $tenant->status = TenantStatus::Suspended;
            $tenant->suspended_at = now();
            $tenant->suspension_reason = 'trial_ended';
            $tenant->save();
            $n++;
        }

        return $n;
    }

    public function confirm(Payment $payment, SuperAdmin $by): Invoice
    {
        return DB::connection('central')->transaction(function () use ($payment, $by) {
            $payment->update(['status' => 'confirmed', 'confirmed_by' => $by->id, 'confirmed_at' => now()]);
            $invoice = $payment->invoice;
            $paid = (int) $invoice->payments()->where('status', 'confirmed')->sum('amount');
            if ($paid >= $invoice->amount) {
                $invoice->update(['status' => 'paid', 'paid_at' => now()]);
                $tenant = $invoice->tenant;
                $stillOverdue = Invoice::query()->where('tenant_id', $tenant->id)->where('status', 'open')
                    ->where('due_on', '<', now()->subDays((int) config('hazri.billing.grace_days', 15)))->exists();
                if ($tenant->status === TenantStatus::Suspended && $tenant->suspension_reason === 'billing' && ! $stillOverdue) {
                    $tenant->status = TenantStatus::Active;
                    $tenant->suspended_at = null;
                    $tenant->suspension_reason = null;
                    $tenant->save();
                }
            }

            return $invoice->fresh();
        });
    }

    /** Called when an employee is created in tenant context: refuse beyond the tenant / plan limit. */
    public static function assertEmployeeCapacity(): void
    {
        $left = self::employeeCapacityLeft();
        if ($left !== null && $left <= 0) {
            throw ValidationException::withMessages(['code' => __('Your plan allows :n active employees. Mark someone as left or upgrade the plan.', ['n' => self::employeeLimit()])]);
        }
    }

    /** How many more active employees the company can have under its tenant / plan limit; null = no limit. */
    public static function employeeCapacityLeft(): ?int
    {
        $limit = self::employeeLimit();
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - Employee::query()->where('status', '!=', EmployeeStatus::Left)->count());
    }

    /** The company's own employee limit, else its plan's; null = unlimited (or outside a company). */
    public static function employeeLimit(): ?int
    {
        $tenant = tenant();
        $limit = $tenant?->employee_limit ?? ($tenant?->plan?->limits['employees'] ?? null);

        return $limit === null ? null : (int) $limit;
    }
}
