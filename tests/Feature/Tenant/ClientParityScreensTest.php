<?php

declare(strict_types=1);

use App\Enums\ExemptionType;
use App\Filament\Tenant\Pages\CompanySettings;
use App\Filament\Tenant\Pages\Roster;
use App\Filament\Tenant\Resources\AttendanceExemptions\Pages\ManageAttendanceExemptions;
use App\Filament\Tenant\Resources\Holidays\Pages\ManageHolidays;
use App\Filament\Tenant\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Filament\Tenant\Resources\Punches\Pages\ListPunches;
use App\Models\AttendanceExemption;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Punch;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

/* The modal forms behind the new legacy-parity features, driven the way an HR user would. */

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $this->actingAs($this->owner);
});

it('saves the pay month, leave year and late adjustment settings', function () {
    Livewire::test(CompanySettings::class)
        ->fillForm(['pay_cycle_start_day' => 21, 'leave_year_start_month' => 10, 'late_adjustment_mode' => 'leave', 'late_adjustment_leave_type' => 'CL'])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(TenantSettings::class);
    $settings->flush();
    expect((int) $settings->get('pay_cycle_start_day'))->toBe(21)
        ->and((int) $settings->get('leave_year_start_month'))->toBe(10)
        ->and($settings->get('late_adjustment_leave_type'))->toBe('CL');
});

it('requires a leave type when lates are charged to leave', function () {
    Livewire::test(CompanySettings::class)
        ->fillForm(['late_adjustment_mode' => 'leave', 'late_adjustment_leave_type' => null])
        ->call('save')
        ->assertHasFormErrors(['late_adjustment_leave_type' => 'required']);
});

it('records who created an exemption', function () {
    Livewire::test(ManageAttendanceExemptions::class)
        ->callAction('create', data: ['date' => '2026-09-15', 'type' => ExemptionType::Absent->value, 'reason' => 'Transport strike', 'scope' => 'tenant'])
        ->assertHasNoActionErrors();

    expect(AttendanceExemption::where('reason', 'Transport strike')->value('created_by'))->toBe($this->owner->id);
});

it('adds a holiday for every day of a range', function () {
    Livewire::test(ManageHolidays::class)
        ->callAction('range', data: ['name' => 'Test Eid range', 'from' => '2027-03-20', 'to' => '2027-03-23', 'type' => 'gazetted', 'is_paid' => true])
        ->assertHasNoActionErrors();

    expect(Holiday::where('name', 'Test Eid range')->pluck('date')->map->format('Y-m-d')->sort()->values()->all())
        ->toBe(['2027-03-20', '2027-03-21', '2027-03-22', '2027-03-23']);
});

it('saves grade entitlements with a leave type', function () {
    $undoRepeaterFake = Repeater::fake();
    $grade = Grade::create(['code' => 'OFF', 'name' => 'Officer']);

    Livewire::test(ManageLeaveTypes::class)
        ->callAction('create', data: ['name' => 'Leaves', 'code' => 'LV', 'annual_quota_days' => 30, 'accrual' => 'monthly',
            'allow_negative_balance' => true, 'entitlements' => [['grade_id' => $grade->id, 'employment_type' => null, 'days_per_year' => 20]]])
        ->assertHasNoActionErrors();
    $undoRepeaterFake();

    $type = LeaveType::where('code', 'LV')->firstOrFail();
    expect($type->allow_negative_balance)->toBeTrue()
        ->and($type->entitlements()->first()?->days_per_year)->toBe(20.0);
});

it('generates a monthly roster from the roster screen', function () {
    $night = Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '20:00', 'end_time' => '04:00', 'crosses_midnight' => true]);
    $e = Employee::create(['code' => 'UI1', 'first_name' => 'Ui', 'joined_on' => '2025-01-01']);

    Livewire::test(Roster::class)
        ->callAction('generateMonth', data: ['month' => '2026-11', 'shift_id' => $night->id, 'scope' => 'all'])
        ->assertHasNoActionErrors();

    expect(ShiftAssignment::where(['employee_id' => $e->id, 'shift_id' => $night->id])->value('effective_to')?->format('Y-m-d'))->toBe('2026-11-30');
});

it('enters manual attendance over a date range from the punches screen', function () {
    Holiday::query()->delete();
    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00', 'Asia/Karachi'));
    $e = Employee::create(['code' => 'UI2', 'first_name' => 'Ui', 'joined_on' => '2025-01-01']);

    Livewire::test(ListPunches::class)
        ->callAction('manualRange', data: ['employee_id' => $e->id, 'from' => '2026-09-14', 'to' => '2026-09-16',
            'in_time' => '09:00', 'out_time' => '18:00', 'reason' => 'Machine down', 'working_days_only' => true])
        ->assertHasNoActionErrors();

    expect(Punch::where('employee_id', $e->id)->count())->toBe(6);
});
