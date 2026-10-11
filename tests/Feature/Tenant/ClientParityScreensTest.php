<?php

declare(strict_types=1);

use App\Enums\ExemptionType;
use App\Filament\Tenant\Pages\CompanySettings;
use App\Filament\Tenant\Pages\Roster;
use App\Filament\Tenant\Resources\AttendanceExemptions\Pages\ManageAttendanceExemptions;
use App\Filament\Tenant\Resources\Branches\Pages\ManageBranches;
use App\Filament\Tenant\Resources\Holidays\Pages\ManageHolidays;
use App\Filament\Tenant\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Filament\Tenant\Resources\Punches\Pages\ListPunches;
use App\Filament\Tenant\Resources\Shifts\Pages\CreateShift;
use App\Models\AttendanceExemption;
use App\Models\Branch;
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
use Illuminate\Database\UniqueConstraintViolationException;
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

it('shows each blank roster cell as the usual shift by name and adds shifts from the roster', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00', 'Asia/Karachi'));
    $night = Shift::create(['name' => 'Night', 'code' => 'NGT', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true]);
    $e = Employee::create(['code' => 'UI3', 'first_name' => 'Ui', 'joined_on' => '2025-01-01']);
    ShiftAssignment::create(['employee_id' => $e->id, 'shift_id' => $night->id, 'effective_from' => '2026-11-01', 'effective_to' => '2026-11-30']);
    ShiftAssignment::create(['employee_id' => $e->id, 'shift_id' => Shift::where('is_default', true)->value('id'), 'effective_from' => '2026-11-05', 'effective_to' => '2026-11-05']);

    $page = Livewire::test(Roster::class);
    expect($page->instance()->getUsualShifts()[$e->id]['2026-11-04'])->toBe('Night (22:00–06:00)');
    $page->assertSee('Usual: Night (22:00–06:00)')->assertSee('hz-roster-changed', escape: false);

    $page->callAction('newShift', data: ['name' => 'Evening', 'code' => 'EVE', 'start_time' => '14:00', 'end_time' => '22:00', 'break_min' => 30])
        ->assertHasNoActionErrors();
    expect(Shift::where('code', 'EVE')->value('break_min'))->toBe(30)
        ->and($page->instance()->getShiftOptions())->toContain('Evening (14:00–22:00)');
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

it('stores no weekly offs as blank on a shift and a branch so the fallback applies', function () {
    Livewire::test(CreateShift::class)
        ->fillForm(['name' => 'Late', 'code' => 'LTE', 'start_time' => '11:00', 'end_time' => '20:00', 'weekly_off_days' => []])
        ->call('create')
        ->assertHasNoFormErrors();
    Livewire::test(ManageBranches::class)
        ->callAction('create', data: ['name' => 'North', 'code' => 'NTH', 'weekly_off_days' => []])
        ->assertHasNoActionErrors();

    $shift = Shift::where('code', 'LTE')->firstOrFail();
    expect($shift->weekly_off_days)->toBeNull()
        ->and(Branch::where('code', 'NTH')->value('weekly_off_days'))->toBeNull()
        ->and($shift->toResolved([5])->weeklyOffDays)->toBe([5]);
});

it('refuses a second holiday on the same date for the same branches', function () {
    $khi = Branch::create(['name' => 'Karachi', 'code' => 'KHU']);
    Holiday::create(['date' => '2027-04-07', 'name' => 'First']);

    Livewire::test(ManageHolidays::class)
        ->callAction('create', data: ['date' => '2027-04-07', 'name' => 'Again', 'type' => 'company', 'is_paid' => true])
        ->assertHasActionErrors(['date' => 'unique']);
    Livewire::test(ManageHolidays::class)
        ->callAction('create', data: ['date' => '2027-04-07', 'name' => 'Karachi only', 'type' => 'company', 'branch_id' => $khi->id, 'is_paid' => true])
        ->assertHasNoActionErrors();

    expect(Holiday::whereDate('date', '2027-04-07')->count())->toBe(2)
        ->and(fn () => Holiday::create(['date' => '2027-04-07', 'name' => 'Sneaky']))->toThrow(UniqueConstraintViolationException::class);
});
