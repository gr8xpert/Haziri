<?php

declare(strict_types=1);

use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Enums\TenantStatus;
use App\Filament\Tenant\Pages\Branding;
use App\Models\Central\DeviceRegistry;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\SuperAdmin;
use App\Models\Central\UnknownDevice;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->plan = Plan::where('code', 'starter')->first() ?? Plan::create(['code' => 'starter', 'name' => 'Starter', 'price_per_employee' => 150, 'limits' => ['employees' => 25]]);
    $this->plan->update(['price_per_employee' => 150, 'price_fixed' => 1000]);
    $this->tenant->update(['plan_id' => $this->plan->id, 'status' => TenantStatus::Active]);
    Employee::create(['code' => 'BL1', 'first_name' => 'One', 'joined_on' => '2025-01-01']);
    Employee::create(['code' => 'BL2', 'first_name' => 'Two', 'joined_on' => '2025-01-01']);
});

afterEach(function () {
    Invoice::where('tenant_id', $this->tenant->id)->delete();
    $this->tenant->update(['plan_id' => null, 'status' => TenantStatus::Trial, 'employee_limit' => null, 'suspended_at' => null]);
    UnknownDevice::where('serial_number', 'NEWSN1')->delete();
    DeviceRegistry::where('serial_number', 'NEWSN1')->delete();
});

it('invoices active employees at the plan price, once per month', function () {
    $billing = app(BillingService::class);
    $month = CarbonImmutable::parse('2026-10-01');

    $billing->invoiceMonth($month);
    $billing->invoiceMonth($month);

    $invoice = Invoice::where('tenant_id', $this->tenant->id)->sole();
    expect($invoice->employee_count)->toBeGreaterThanOrEqual(2)
        ->and($invoice->amount)->toBe($invoice->employee_count * 150 + 1000)
        ->and($invoice->due_on->format('Y-m-d'))->toBe('2026-10-11');
});

it('suspends after the grace period and reactivates when paid', function () {
    $billing = app(BillingService::class);
    $billing->invoiceMonth(CarbonImmutable::parse('2026-07-01'));
    $invoice = Invoice::where('tenant_id', $this->tenant->id)->sole();

    expect($billing->enforce(CarbonImmutable::parse('2026-07-20')))->toBe(0); // within due + grace
    expect($billing->enforce(CarbonImmutable::parse('2026-08-01')))->toBe(1);
    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Suspended);

    $payment = $invoice->payments()->create(['amount' => $invoice->amount, 'method' => 'jazzcash', 'reference' => 'JC123']);
    $billing->confirm($payment, SuperAdmin::firstOrCreate(['email' => 'billing@hazri.test'], ['name' => 'B', 'password' => 'x']));

    expect($invoice->fresh()->status)->toBe('paid')->and($this->tenant->fresh()->status)->toBe(TenantStatus::Active);
});

it('blocks new employees beyond the plan limit', function () {
    $this->tenant->update(['employee_limit' => Employee::where('status', '!=', 'left')->count()]);
    tenancy()->tenant->refresh();

    Employee::create(['code' => 'OVER', 'first_name' => 'Over', 'joined_on' => '2025-01-01']);
})->throws(ValidationException::class);

it('attaches an unknown ADMS device to a company', function () {
    UnknownDevice::record('NEWSN1', '1.2.3.4');

    $this->tenant->run(fn () => Device::create(['name' => 'Gate', 'serial_number' => 'NEWSN1', 'type' => 'adms']));

    expect(UnknownDevice::where('serial_number', 'NEWSN1')->exists())->toBeFalse()
        ->and(DeviceRegistry::where('serial_number', 'NEWSN1')->value('tenant_id'))->toBe($this->tenant->id);
});

it('validates custom domains and applies white-label settings', function () {
    Setting::updateOrCreate(['key' => 'feature.white_label'], ['value' => true]);
    app(TenantSettings::class)->flush();
    $this->actingAs(User::where('email', 'owner@pest.test')->first());

    Livewire::test(Branding::class)->set('newDomain', 'x.' . config('hazri.central_domain'))->call('addDomain')->assertHasErrors('newDomain');
    Livewire::test(Branding::class)->set('newDomain', 'attendance.acme-test.pk')->call('addDomain')->assertHasNoErrors();
    expect($this->tenant->domains()->where('domain', 'attendance.acme-test.pk')->exists())->toBeTrue();
    $this->tenant->domains()->where('domain', 'attendance.acme-test.pk')->delete();

    Setting::updateOrCreate(['key' => 'brand_color'], ['value' => '#7c3aed']);
    app(TenantSettings::class)->flush();
    $this->get('http://pest.' . config('hazri.central_domain') . '/admin')->assertOk();
    expect(FilamentColor::getColor('primary'))->not->toBeNull();
});

it('never invoices or auto-suspends a white-label client, who is billed outside the app', function () {
    $flags = app(SetTenantFeatureFlags::class);
    $original = SetTenantFeatureFlags::current($this->tenant);
    $billing = app(BillingService::class);

    try {
        $billing->invoiceMonth(CarbonImmutable::parse('2026-07-01')); // an old open invoice from before white-label
        $flags($this->tenant, ['white_label' => true]);

        expect($billing->invoiceMonth(CarbonImmutable::parse('2026-08-01')))->toBe(0)
            ->and($billing->enforce(CarbonImmutable::parse('2026-09-01')))->toBe(0)
            ->and($this->tenant->fresh()->status)->toBe(TenantStatus::Active);
    } finally {
        $flags($this->tenant, $original);
    }
});
