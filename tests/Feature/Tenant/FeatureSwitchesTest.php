<?php

declare(strict_types=1);

use App\Actions\Tenants\SetTenantFeatureFlags;
use App\Enums\Role;
use App\Filament\Tenant\Pages\LeaveCalendar;
use App\Filament\Tenant\Pages\Reports\LeaveBalanceSheet;
use App\Filament\Tenant\Pages\Reports\LeaveRegister;
use App\Filament\Tenant\Pages\Reports\MonthlyLeaveSummary;
use App\Filament\Tenant\Resources\LeaveBalances\LeaveBalanceResource;
use App\Filament\Tenant\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Tenant\Resources\LeaveTypes\LeaveTypeResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;

it('applies connected switches when the super-admin changes a client and mirrors them into the client', function () {
    $original = SetTenantFeatureFlags::current($this->tenant);
    $set = app(SetTenantFeatureFlags::class);

    try {
        $set($this->tenant, ['payroll_lite' => true, 'payroll_tax' => true, 'kiosk' => true, 'kiosk_face' => true]);
        $result = $set($this->tenant, ['payroll_lite' => false, 'kiosk' => false]);

        $settings = app(TenantSettings::class);
        $settings->flush();
        expect($result['turned_off'])->toEqualCanonicalizing(['payroll_tax', 'kiosk_face'])
            ->and(SetTenantFeatureFlags::current($this->tenant))->toMatchArray(['payroll_tax' => false, 'kiosk_face' => false])
            ->and($settings->featureEnabled('payroll_tax'))->toBeFalse()
            ->and($settings->featureEnabled('kiosk_face'))->toBeFalse();
    } finally {
        $set($this->tenant, $original); // central settings are not rolled back with the tenant transaction
    }
});

it('falls back to the catalog default for a switch a client has never had set', function () {
    $settings = app(TenantSettings::class);
    Setting::query()->where('key', 'feature.rosters')->delete();
    $settings->flush();

    expect($settings->featureEnabled('rosters'))->toBeTrue()      // existed before it had a switch
        ->and($settings->featureEnabled('payroll_pf'))->toBeFalse();
});

it('hides every leave screen, report and staff-app leave tab when Leave management is off', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Karachi'));
    $base = 'http://pest.' . config('hazri.central_domain');
    $employee = Employee::create(['code' => 'LVOFF', 'first_name' => 'Lina', 'joined_on' => '2025-01-01']);
    $staff = User::create(['name' => 'Lina', 'email' => 'lina@pest.test', 'password' => 'x', 'employee_id' => $employee->id]);
    $staff->assignRole(Role::Employee->value);
    $leaveType = LeaveType::where('code', 'CL')->firstOrFail();
    $pages = [LeaveRequestResource::class, LeaveBalanceResource::class, LeaveTypeResource::class, LeaveCalendar::class,
        LeaveRegister::class, LeaveBalanceSheet::class, MonthlyLeaveSummary::class];

    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
    expect(collect($pages)->every(fn (string $page) => $page::canAccess()))->toBeTrue();

    Setting::updateOrCreate(['key' => 'feature.leave'], ['value' => false]);
    app(TenantSettings::class)->flush();
    expect(collect($pages)->filter(fn (string $page) => $page::canAccess())->all())->toBe([]);

    $this->actingAs($staff)->get("{$base}/me")->assertOk()->assertDontSee('Apply for leave');
    $this->getJson("{$base}/me/attendance")->assertJsonPath('leave_enabled', false)->assertJsonPath('balances', [])->assertJsonPath('requests', []);
    $this->postJson("{$base}/me/leave", ['leave_type_id' => $leaveType->id, 'from_date' => '2026-09-10', 'to_date' => '2026-09-10'])
        ->assertStatus(422)->assertJsonValidationErrors('leave');
    expect(LeaveRequest::where('employee_id', $employee->id)->exists())->toBeFalse();
});
