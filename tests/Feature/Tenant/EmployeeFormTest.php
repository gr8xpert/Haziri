<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\Employees\Pages\CreateEmployee;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
| Employee form: an attendance-only company sees the short form; fields for switched-off features are hidden.
*/
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->actingAs(User::where('email', 'owner@pest.test')->firstOrFail());
});

function employeeFormFeatures(bool $on): void
{
    foreach (['kiosk', 'mobile_punch', 'payroll_lite', 'payroll_tax', 'payroll_social', 'payroll_pf'] as $flag) {
        Setting::updateOrCreate(['key' => "feature.{$flag}"], ['value' => $on]);
    }
    app(TenantSettings::class)->flush();
}

it('hides fields that belong to switched-off features', function () {
    employeeFormFeatures(false);

    Livewire::test(CreateEmployee::class)
        ->assertFormFieldIsHidden('payroll_id')
        ->assertFormFieldIsHidden('kiosk_pin')
        ->assertFormFieldIsHidden('mobile_punch_allowed')
        ->assertFormFieldIsHidden('photo_path')
        ->assertFormFieldIsHidden('ntn')
        ->assertFormFieldIsHidden('branch_id')
        ->assertFormFieldIsHidden('contractor_id')
        ->assertFormFieldIsVisible('device_pin');
});

it('shows them once the features are on', function () {
    employeeFormFeatures(true);
    Branch::create(['name' => 'Head office', 'code' => 'HO']);
    Branch::create(['name' => 'Factory', 'code' => 'FA']);

    Livewire::test(CreateEmployee::class)
        ->assertFormFieldIsVisible('payroll_id')
        ->assertFormFieldIsVisible('kiosk_pin')
        ->assertFormFieldIsVisible('mobile_punch_allowed')
        ->assertFormFieldIsVisible('photo_path')
        ->assertFormFieldIsVisible('ntn')
        ->assertFormFieldIsVisible('branch_id');
});

it('creates an employee from the main fields, with the picked shift and the only branch', function () {
    employeeFormFeatures(false);
    $branch = Branch::create(['name' => 'Head office', 'code' => 'HO']);
    $night = Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '20:00', 'end_time' => '04:00', 'crosses_midnight' => true]);

    Livewire::test(CreateEmployee::class)
        ->fillForm(['code' => 'F001', 'first_name' => 'Hamza', 'joined_on' => '2026-09-01', 'shift_id' => $night->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::where('code', 'F001')->firstOrFail();
    expect($employee->branch_id)->toBe($branch->id)
        ->and(ShiftAssignment::where(['employee_id' => $employee->id, 'shift_id' => $night->id])->value('effective_from')?->format('Y-m-d'))
        ->toBe('2026-09-01');
});
