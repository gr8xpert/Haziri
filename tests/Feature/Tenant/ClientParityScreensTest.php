<?php

declare(strict_types=1);

use App\Enums\ExemptionType;
use App\Filament\Tenant\Pages\CompanySettings;
use App\Filament\Tenant\Pages\DailySheet;
use App\Filament\Tenant\Pages\Roster;
use App\Filament\Tenant\Resources\AttendanceExemptions\Pages\ManageAttendanceExemptions;
use App\Filament\Tenant\Resources\Branches\Pages\ManageBranches;
use App\Filament\Tenant\Resources\Grades\GradeResource;
use App\Filament\Tenant\Resources\Holidays\Pages\ManageHolidays;
use App\Filament\Tenant\Resources\LeaveTypes\Pages\ManageLeaveTypes;
use App\Filament\Tenant\Resources\Punches\Pages\ListPunches;
use App\Filament\Tenant\Resources\Shifts\Pages\CreateShift;
use App\Filament\Tenant\Resources\Shifts\Pages\ListShifts;
use App\Models\AttendanceExemption;
use App\Models\AttendancePeriod;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Punch;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceRecomputer;
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

it('refuses to add or remove a single punch in a locked month', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00', 'Asia/Karachi'));
    $e = Employee::create(['code' => 'UI4', 'first_name' => 'Ui', 'joined_on' => '2025-01-01']);
    $punch = Punch::create(['employee_id' => $e->id, 'punched_at' => '2026-08-10 04:00:00', 'local_date' => '2026-08-10', 'source' => 'adms',
        'direction' => 'in', 'status' => 'valid', 'dedupe_hash' => md5('locked-ui4')]);
    AttendancePeriod::create(['year' => 2026, 'month' => 8, 'status' => 'locked']);

    Livewire::test(ListPunches::class)
        ->callAction('manual', data: ['employee_id' => $e->id, 'date' => '2026-08-11', 'in_time' => '09:00', 'reason' => 'Machine down'])
        ->assertNotified('That month is locked.');
    Livewire::test(ListPunches::class)
        ->callTableAction('delete', $punch, data: ['reason' => 'Wrong person'])
        ->assertNotified('That month is locked.');

    expect(Punch::where('employee_id', $e->id)->count())->toBe(1)
        ->and($punch->fresh()->trashed())->toBeFalse();
});

it('replaces the day when Manual Entry changes a time that was already recorded', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00', 'Asia/Karachi'));
    $e = Employee::create(['code' => 'UI5', 'first_name' => 'Ui', 'joined_on' => '2025-01-01']);
    foreach (['04:00:00', '13:30:00'] as $utc) {
        Punch::create(['employee_id' => $e->id, 'punched_at' => "2026-09-15 {$utc}", 'local_date' => '2026-09-15', 'source' => 'adms',
            'direction' => 'unknown', 'status' => 'valid', 'dedupe_hash' => md5("ui5-{$utc}")]);
    }
    $lastOut = function () use ($e): string {
        $day = app(AttendanceRecomputer::class)->recompute($e->id, '2026-09-15');

        return $day->last_out->setTimezone('Asia/Karachi')->format('H:i');
    };
    expect($lastOut())->toBe('18:30');

    $sheet = Livewire::test(DailySheet::class)->set('date', '2026-09-15')
        ->assertSet("rows.{$e->id}.out", '18:30')
        ->set("rows.{$e->id}.out", '17:00')->call('save');
    expect($lastOut())->toBe('17:00')
        ->and(Punch::where('employee_id', $e->id)->where('is_manual', false)->count())->toBe(2);
    $sheet->assertSet("rows.{$e->id}.in", '09:00')->assertSet("rows.{$e->id}.out", '17:00');

    $sheet->set("rows.{$e->id}.out", '18:30')->call('save');
    expect($lastOut())->toBe('18:30')
        ->and(Punch::where('employee_id', $e->id)->where('is_manual', true)->count())->toBe(2)
        ->and(Punch::onlyTrashed()->where('employee_id', $e->id)->count())->toBe(2);
});

it('says how many Manual Entry rows were and were not saved, without a success message', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00', 'Asia/Karachi'));
    $e = Employee::create(['code' => 'UI6', 'first_name' => 'Ui', 'joined_on' => '2025-01-01']);
    AttendancePeriod::create(['year' => 2026, 'month' => 8, 'status' => 'locked']);

    Livewire::test(DailySheet::class)->set('date', '2026-08-10')
        ->set("rows.{$e->id}.in", '09:00')->call('save')
        ->assertNotified('0 saved, 1 not saved')
        ->assertNotNotified('0 employees updated');
});

it('shows leave, holiday and off day instead of Absent for empty Manual Entry rows', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00', 'Asia/Karachi'));
    Holiday::query()->delete();
    $onLeave = Employee::create(['code' => 'UI7', 'first_name' => 'Ui', 'joined_on' => '2025-01-01']);
    $absent = Employee::create(['code' => 'UI8', 'first_name' => 'Ui', 'joined_on' => '2025-01-01']);
    $type = LeaveType::create(['name' => 'Casual leave', 'code' => 'CLU', 'annual_quota_days' => 10]);
    LeaveRequest::create(['employee_id' => $onLeave->id, 'leave_type_id' => $type->id, 'from_date' => '2026-09-15', 'to_date' => '2026-09-15',
        'days' => 1, 'status' => 'approved']);
    Holiday::create(['date' => '2026-09-16', 'name' => 'Company day']);

    Livewire::test(DailySheet::class)
        ->set('date', '2026-09-15')->assertSet("rows.{$onLeave->id}.day_off", 'Leave')->assertSet("rows.{$absent->id}.day_off", null)
        ->assertSee('Leave')->assertSee('Absent')
        ->set('date', '2026-09-16')->assertSet("rows.{$absent->id}.day_off", 'Holiday')
        ->set('date', '2026-09-13')->assertSet("rows.{$absent->id}.day_off", 'Off day');
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

it('warns how many employees use a shift before deleting it and frees its code', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Karachi'));
    $old = Shift::create(['name' => 'Old', 'code' => 'OLD', 'start_time' => '07:00', 'end_time' => '15:00']);
    foreach (['DL1', 'DL2'] as $code) {
        $e = Employee::create(['code' => $code, 'first_name' => $code, 'joined_on' => '2025-01-01']);
        ShiftAssignment::create(['employee_id' => $e->id, 'shift_id' => $old->id, 'effective_from' => '2026-01-01']);
    }

    $list = Livewire::test(ListShifts::class)->mountTableAction('delete', $old);
    expect($list->instance()->getMountedAction()->getModalDescription())->toContain('2 employees are on this shift');
    $list->callMountedAction();

    Livewire::test(CreateShift::class)
        ->fillForm(['name' => 'Old again', 'code' => 'OLD', 'start_time' => '07:00', 'end_time' => '15:00'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Shift::where('code', 'OLD')->value('name'))->toBe('Old again')
        ->and(Shift::onlyTrashed()->find($old->id)->code)->toBe("OLD~{$old->id}");
});

it('warns how many leave entitlements and employees a grade takes with it before deleting it', function () {
    $grade = Grade::create(['code' => 'WRK', 'name' => 'Worker']);
    foreach (LeaveType::query()->whereIn('code', ['CL', 'SL'])->get() as $type) {
        $type->entitlements()->create(['grade_id' => $grade->id, 'days_per_year' => 5]);
    }
    Employee::create(['code' => 'GR1', 'first_name' => 'Graded', 'joined_on' => '2025-01-01', 'grade_id' => $grade->id]);

    expect(GradeResource::deleteWarning($grade))->toContain('Its 2 leave entitlements on your leave types are deleted too.')
        ->toContain('1 employee loses this grade.');
});
