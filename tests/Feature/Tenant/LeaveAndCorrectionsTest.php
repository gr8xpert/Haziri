<?php

declare(strict_types=1);

use App\Actions\Corrections\DecideCorrection;
use App\Actions\Corrections\SubmitCorrection;
use App\Actions\Leave\CancelLeaveRequest;
use App\Actions\Leave\DecideLeaveRequest;
use App\Actions\Leave\SubmitLeaveRequest;
use App\Actions\Periods\PeriodActions;
use App\Actions\Punches\CreateManualPunches;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionType;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveDayPart;
use App\Enums\PeriodStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Filament\Tenant\Pages\LeaveCalendar;
use App\Filament\Tenant\Resources\Corrections\Pages\ListCorrections;
use App\Filament\Tenant\Resources\LeaveBalances\Pages\ListLeaveBalances;
use App\Filament\Tenant\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeLeaveRule;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\MissingPunchSuggester;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function person(string $code, array $attrs = []): Employee
{
    return Employee::create(array_merge(['code' => $code, 'first_name' => $code, 'joined_on' => '2025-01-01'], $attrs));
}

function login(string $email, Role $role, ?Employee $employee = null): User
{
    $u = User::create(['name' => $email, 'email' => $email, 'password' => 'x', 'employee_id' => $employee?->id]);
    $u->assignRole($role->value);

    return $u;
}

function dayStatus(Employee $e, string $date): ?AttendanceStatus
{
    return AttendanceDay::where('employee_id', $e->id)->whereDate('date', $date)->value('status');
}

beforeEach(function () {
    $this->boss = person('BOSS');
    $this->emp = person('EMP', ['manager_employee_id' => $this->boss->id]);
    $this->manager = login('mgr@pest.test', Role::Manager, $this->boss);
    $this->hr = login('hr@pest.test', Role::Hr);
    $this->self = login('emp@pest.test', Role::Employee, $this->emp);
    $this->cl = LeaveType::where('code', 'CL')->first();
    $this->al = LeaveType::where('code', 'AL')->first();
    $this->sl = LeaveType::where('code', 'SL')->first();
    $this->submit = app(SubmitLeaveRequest::class);
    $this->decide = app(DecideLeaveRequest::class);
});

it('counts working days only and routes through manager then HR', function () {
    Holiday::create(['date' => '2026-09-02', 'name' => 'Test holiday']);
    // Tue 1 → Mon 7 Sep: Sun 6 is off, Wed 2 is a holiday → 5 working days
    $r = ($this->submit)($this->emp, $this->cl, '2026-09-01', '2026-09-07', $this->self);

    expect($r->days)->toBe(5.0)
        ->and(array_column($r->approver_chain, 'step'))->toBe(['manager', 'hr'])
        ->and(LeaveBalance::where(['employee_id' => $this->emp->id, 'leave_type_id' => $this->cl->id])->value('pending'))->toBe(5.0);

    ($this->decide)($r, $this->manager, true);
    expect($r->fresh()->status)->toBe(RequestStatus::Pending)->and($r->fresh()->current_step)->toBe(1);
    // (an HR-level approval at step 0 would have completed the chain in one go)

    expect(fn () => ($this->decide)($r->fresh(), $this->manager, true))->toThrow(ValidationException::class); // HR step now

    ($this->decide)($r->fresh(), $this->hr, true, 'ok');
    $balance = LeaveBalance::where(['employee_id' => $this->emp->id, 'leave_type_id' => $this->cl->id])->first();
    expect($r->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($balance->pending)->toBe(0.0)->and($balance->used)->toBe(5.0)
        ->and(dayStatus($this->emp, '2026-09-03'))->toBe(AttendanceStatus::LeavePaid)
        ->and(dayStatus($this->emp, '2026-09-02'))->toBe(AttendanceStatus::Holiday);
});

it('skips the manager step when there is no manager login', function () {
    $loner = person('LONE');
    $r = ($this->submit)($loner, $this->cl, '2026-09-01', '2026-09-01', $this->hr);

    expect(array_column($r->approver_chain, 'step'))->toBe(['hr']);
});

it('requires a reason to reject and releases the pending balance', function () {
    $r = ($this->submit)($this->emp, $this->cl, '2026-09-01', '2026-09-01', $this->self);
    expect(fn () => ($this->decide)($r, $this->hr, false))->toThrow(ValidationException::class);

    ($this->decide)($r->fresh(), $this->hr, false, 'busy week');

    expect($r->fresh()->status)->toBe(RequestStatus::Rejected)
        ->and(LeaveBalance::where(['employee_id' => $this->emp->id, 'leave_type_id' => $this->cl->id])->value('pending'))->toBe(0.0);
});

it('names the employment type in words when a leave type does not apply', function () {
    $this->cl->update(['applies_to' => ['permanent']]);
    $wager = person('DW1', ['employment_type' => 'daily_wage']);

    expect(fn () => $this->submit->__invoke($wager, $this->cl, '2026-09-08', '2026-09-08', $this->hr))
        ->toThrow(ValidationException::class, 'This leave type does not apply to Daily wage employees.');
});

it('enforces balance, overlap, attachment and half-day rules', function () {
    expect(fn () => ($this->submit)($this->emp, $this->cl, '2026-09-01', '2026-09-30', $this->self))->toThrow(ValidationException::class);
    ($this->submit)($this->emp, $this->cl, '2026-09-01', '2026-09-01', $this->self);
    expect(fn () => ($this->submit)($this->emp, $this->cl, '2026-09-01', '2026-09-01', $this->self))->toThrow(ValidationException::class)
        ->and(fn () => ($this->submit)($this->emp, $this->sl, '2026-09-08', '2026-09-10', $this->self))->toThrow(ValidationException::class)
        ->and(fn () => ($this->submit)($this->emp, $this->al, '2026-09-08', '2026-09-09', $this->self, LeaveDayPart::FirstHalf))->toThrow(ValidationException::class);

    $half = ($this->submit)($this->emp, $this->cl, '2026-09-10', '2026-09-10', $this->self, LeaveDayPart::FirstHalf);
    expect($half->days)->toBe(0.5);
});

it('applies the sandwich rule on approval and reverses it on cancel', function () {
    Shift::where('is_default', true)->first()->update(['weekly_off_days' => [0, 6]]);
    $fri = ($this->submit)($this->emp, $this->al, '2026-09-04', '2026-09-04', $this->hr);
    ($this->decide)($fri, $this->hr, true);
    $mon = ($this->submit)($this->emp, $this->al, '2026-09-07', '2026-09-07', $this->hr);
    ($this->decide)($mon, $this->hr, true);

    $balance = fn () => LeaveBalance::where(['employee_id' => $this->emp->id, 'leave_type_id' => $this->al->id])->first();
    expect($mon->fresh()->sandwich_dates)->toBe(['2026-09-05', '2026-09-06'])
        ->and($balance()->used)->toBe(4.0)
        ->and(dayStatus($this->emp, '2026-09-05'))->toBe(AttendanceStatus::LeavePaid);

    app(CancelLeaveRequest::class)($mon->fresh(), $this->hr, 'changed plans');
    expect($balance()->used)->toBe(1.0)->and(dayStatus($this->emp, '2026-09-05'))->toBe(AttendanceStatus::WeeklyOff);
});

it('accrues monthly leave and caps carry-forward', function () {
    $monthly = LeaveType::create(['name' => 'Monthly', 'code' => 'MO', 'annual_quota_days' => 12, 'accrual' => 'monthly', 'carry_forward_max' => 7]);
    $svc = app(LeaveBalanceService::class);
    $joiner = person('NEW', ['joined_on' => '2026-03-15']);

    expect($svc->accrued($this->emp, $monthly, 2026, CarbonImmutable::parse('2026-06-10')))->toBe(6.0)
        ->and($svc->accrued($joiner, $this->al, 2026))->toBe(11.5); // 14 × 10/12 = 11.67 → 11.5

    LeaveBalance::create(['employee_id' => $this->emp->id, 'leave_type_id' => $monthly->id, 'year' => 2025, 'accrued' => 12, 'used' => 3]);
    $svc->openYear(2026);
    expect(LeaveBalance::where(['employee_id' => $this->emp->id, 'leave_type_id' => $monthly->id, 'year' => 2026])->value('opening'))->toBe(7.0);
});

it('lets a grade row set its own carry forward and longest request', function () {
    $officer = Grade::create(['code' => 'OFF', 'name' => 'Officer']);
    $this->al->update(['carry_forward_max' => 7, 'max_consecutive_days' => 10]);
    LeaveEntitlement::create(['leave_type_id' => $this->al->id, 'grade_id' => $officer->id, 'days_per_year' => 20,
        'carry_forward_max' => 3, 'max_consecutive_days' => 2]);
    $this->emp->update(['grade_id' => $officer->id]);
    $worker = person('WRK');
    $svc = app(LeaveBalanceService::class);

    expect($svc->rules($this->emp->fresh(), $this->al))->toBe(['quota' => 20.0, 'carry_forward_max' => 3.0, 'max_consecutive_days' => 2])
        ->and($svc->rules($worker, $this->al))->toBe(['quota' => 14.0, 'carry_forward_max' => 7.0, 'max_consecutive_days' => 10]);

    foreach ([$this->emp, $worker] as $e) {
        LeaveBalance::create(['employee_id' => $e->id, 'leave_type_id' => $this->al->id, 'year' => 2025, 'accrued' => 14, 'used' => 0]);
    }
    $svc->openYear(2026);
    $opening = fn (Employee $e) => LeaveBalance::where(['employee_id' => $e->id, 'leave_type_id' => $this->al->id, 'year' => 2026])->value('opening');
    expect($opening($this->emp))->toBe(3.0)->and($opening($worker))->toBe(7.0);

    expect(fn () => ($this->submit)($this->emp->fresh(), $this->al, '2026-09-08', '2026-09-10', $this->hr))
        ->toThrow(ValidationException::class, 'At most 2 consecutive days are allowed.');
});

it('updates this year\'s balances as soon as a grade entitlement changes', function () {
    $officer = Grade::create(['code' => 'OFF', 'name' => 'Officer']);
    $this->emp->update(['grade_id' => $officer->id]);
    $svc = app(LeaveBalanceService::class);
    $year = app(LeaveYear::class)->current();
    $accrued = fn () => LeaveBalance::where(['employee_id' => $this->emp->id, 'leave_type_id' => $this->al->id, 'year' => $year])->value('accrued');
    $svc->balance($this->emp->fresh(), $this->al, $year);
    expect($accrued())->toBe(14.0);

    $row = LeaveEntitlement::create(['leave_type_id' => $this->al->id, 'grade_id' => $officer->id, 'days_per_year' => 20]);
    expect($accrued())->toBe(20.0);

    $row->delete();
    expect($accrued())->toBe(14.0);
});

it('applies approved corrections automatically', function () {
    $submit = app(SubmitCorrection::class);
    $decide = app(DecideCorrection::class);
    app(CreateManualPunches::class)($this->emp, '2026-09-08', '09:00', null, 'forgot out', $this->hr);

    $add = $submit($this->emp, '2026-09-08', CorrectionType::AddPunch, ['time' => '18:05', 'direction' => 'out'], 'forgot', $this->self);
    ($decide)($add, $this->manager, true);
    ($decide)($add->fresh(), $this->hr, true);
    $day = AttendanceDay::where('employee_id', $this->emp->id)->whereDate('date', '2026-09-08')->first();
    expect($day->status)->toBe(AttendanceStatus::Present)->and($day->flags)->not->toContain('missing_out');

    $od = $submit($this->emp, '2026-09-09', CorrectionType::MarkOd, [], 'client visit', $this->manager);
    ($decide)($od, $this->hr, true);
    expect(dayStatus($this->emp, '2026-09-09'))->toBe(AttendanceStatus::OfficialDuty);

    $st = $submit($this->emp, '2026-09-10', CorrectionType::ChangeStatus, ['status' => 'wfh'], 'worked from home', $this->hr);
    ($decide)($st, $this->hr, true);
    expect(dayStatus($this->emp, '2026-09-10'))->toBe(AttendanceStatus::Wfh);
});

it('describes a correction in plain words: what, who asked and where it waits', function () {
    $submit = app(SubmitCorrection::class);
    $add = $submit($this->emp, '2026-09-08', CorrectionType::AddPunch, ['time' => '18:10', 'direction' => 'out'], 'forgot', $this->self);

    expect($add->detailsText())->toBe('Out punch at 18:10')
        ->and($add->requestedByText())->toBe('emp@pest.test (self)')
        ->and($add->stageText())->toBe('Waiting for manager');

    app(DecideCorrection::class)($add, $this->manager, true);
    expect($add->fresh()->stageText())->toBe('Waiting for HR');

    app(DecideCorrection::class)($add->fresh(), $this->hr, true);
    expect($add->fresh()->stageText())->toBe('Approved by hr@pest.test');

    $status = $submit($this->emp, '2026-09-10', CorrectionType::ChangeStatus, ['status' => 'present'], 'was in', $this->hr);
    expect($status->detailsText())->toBe('Change to Present')
        ->and($status->requestedByText())->toBe('hr@pest.test');
});

it('lists a correction once, in plain words', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    app(SubmitCorrection::class)($this->emp, '2026-09-08', CorrectionType::AddPunch, ['time' => '18:10', 'direction' => 'out'], 'forgot', $this->self);
    $this->actingAs($this->hr);

    Livewire::test(ListCorrections::class)
        ->assertSee('Out punch at 18:10')->assertSee('Add punch')->assertSee('Waiting for manager')
        ->assertDontSee('time: 18:10')->assertDontSee('add_punch');
});

it('stops managers from requesting for people outside their team', function () {
    $other = person('OTHER');

    app(SubmitCorrection::class)($other, '2026-09-09', CorrectionType::MarkWfh, [], 'x', $this->manager);
})->throws(ValidationException::class);

it('suggests the median usual out time for a missing punch', function () {
    foreach (['17:55', '18:00', '18:05', '18:10', '18:30', '19:00'] as $i => $t) {
        AttendanceDay::create(['employee_id' => $this->emp->id, 'date' => sprintf('2026-08-%02d', $i + 3), 'status' => AttendanceStatus::Present,
            'last_out' => CarbonImmutable::parse("2026-08-01 {$t}", 'Asia/Karachi')->utc()->format('Y-m-d H:i:s'), 'flags' => []]);
    }

    expect(app(MissingPunchSuggester::class)->suggestOut($this->emp))->toBe('18:07');
});

it('locks a month with a snapshot, blocks changes, and unlocks only with permission and reason', function () {
    app(CreateManualPunches::class)($this->emp, '2026-08-03', '09:00', '18:00', 'entry', $this->hr);
    $owner = User::where('email', 'owner@pest.test')->first();
    $actions = app(PeriodActions::class);
    $period = $actions->period(2026, 8);

    $actions->lock($period, $this->hr);

    expect($period->fresh()->status)->toBe(PeriodStatus::Locked)
        ->and($period->fresh()->summary['employees'][$this->emp->id]['presentDays'])->toBe(1)
        ->and(AttendanceDay::whereDate('date', '2026-08-03')->where('employee_id', $this->emp->id)->value('is_locked'))->toBeTrue()
        ->and(fn () => ($this->submit)($this->emp, $this->cl, '2026-08-10', '2026-08-10', $this->hr))->toThrow(ValidationException::class)
        ->and(fn () => $actions->unlock($period->fresh(), $this->hr, 'oops'))->toThrow(ValidationException::class)
        ->and(fn () => $actions->unlock($period->fresh(), $owner, ''))->toThrow(ValidationException::class);

    $actions->unlock($period->fresh(), $owner, 'late correction');
    expect($period->fresh()->status)->toBe(PeriodStatus::Review);
});

it("shows who's off with leave names, branch-only holidays, filters, and only the team for managers", function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->travelTo(CarbonImmutable::parse('2026-09-09 10:00', 'Asia/Karachi'));
    $khi = Branch::create(['name' => 'Karachi', 'code' => 'KHI']);
    $other = person('OUT', ['branch_id' => $khi->id]);
    foreach ([$this->emp, $other] as $e) {
        LeaveRequest::create(['employee_id' => $e->id, 'leave_type_id' => $this->cl->id, 'from_date' => '2026-09-10', 'to_date' => '2026-09-10',
            'days' => 1, 'status' => RequestStatus::Approved, 'day_part' => LeaveDayPart::Full]);
    }
    Holiday::create(['date' => '2026-09-11', 'name' => 'Local holiday', 'type' => 'company', 'branch_id' => $khi->id]);

    $this->actingAs($this->hr);
    $hr = Livewire::test(LeaveCalendar::class);
    expect(collect($hr->instance()->getDays()['2026-09-10'])->pluck('what')->unique()->all())->toBe(['Casual Leave'])
        ->and(collect($hr->instance()->getDays()['2026-09-10']))->toHaveCount(2)
        ->and($hr->instance()->getDays()['2026-09-11'][0]['what'])->toBe('Holiday, Karachi only');
    $hr->set('branchId', $khi->id);
    expect(collect($hr->instance()->getDays()['2026-09-10'])->pluck('name')->all())->toBe(['OUT']);

    $this->actingAs($this->manager);
    $mgr = Livewire::test(LeaveCalendar::class);
    expect(collect($mgr->instance()->getDays()['2026-09-10'])->pluck('name')->all())->toBe(['EMP']);
});

it("shows weekly offs on who's off and leaves out people who have left", function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->travelTo(CarbonImmutable::parse('2026-09-09 10:00', 'Asia/Karachi'));
    $fridayOff = Shift::create(['name' => 'Friday off', 'code' => 'FRO', 'start_time' => '09:00', 'end_time' => '18:00', 'weekly_off_days' => [5]]);
    $friday = person('FRI');
    ShiftAssignment::create(['employee_id' => $friday->id, 'shift_id' => $fridayOff->id, 'effective_from' => '2026-01-01']);
    $gone = person('GONE', ['status' => EmployeeStatus::Left, 'left_on' => '2026-08-31']);
    LeaveRequest::create(['employee_id' => $gone->id, 'leave_type_id' => $this->cl->id, 'from_date' => '2026-09-10', 'to_date' => '2026-09-10',
        'days' => 1, 'status' => RequestStatus::Approved, 'day_part' => LeaveDayPart::Full]);

    $this->actingAs($this->hr);
    $days = Livewire::test(LeaveCalendar::class)->instance()->getDays();

    expect(collect($days['2026-09-11'])->where('what', 'Off day')->pluck('name')->all())->toBe(['FRI'])
        ->and(collect($days['2026-09-13'])->where('what', 'Off day')->pluck('name')->all())->toContain('BOSS')->not->toContain('FRI')
        ->and(collect($days['2026-09-10'])->pluck('name')->all())->not->toContain('GONE');
});

it('gives one employee a special leave rule that beats the grade row and the leave type', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $this->al->update(['carry_forward_max' => 7]);
    $svc = app(LeaveBalanceService::class);
    $balance = $svc->balance($this->emp, $this->al, 2026);
    $this->actingAs($this->hr);

    Livewire::test(ListLeaveBalances::class)
        ->callAction(TestAction::make('specialRule')->table($balance), data: ['carry_forward_max' => 3, 'days_per_year' => 20, 'reason' => 'Appointment letter'])
        ->assertHasNoActionErrors();

    expect($svc->rules($this->emp, $this->al))->toBe(['quota' => 20.0, 'carry_forward_max' => 3.0, 'max_consecutive_days' => null])
        ->and($svc->rules(person('OTHER2'), $this->al)['carry_forward_max'])->toBe(7.0)
        ->and($balance->fresh()->accrued)->toBe(20.0);

    Livewire::test(ListLeaveBalances::class)
        ->callAction(TestAction::make('specialRule')->table($balance), data: ['carry_forward_max' => null, 'days_per_year' => null, 'max_consecutive_days' => null, 'reason' => 'Back to normal']);
    expect(EmployeeLeaveRule::count())->toBe(0);
});

it('lists leave on which the employee punched anyway', function () {
    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    $leave = ($this->submit)($this->emp, $this->cl, '2026-09-08', '2026-09-08', $this->hr);
    ($this->decide)($leave, $this->hr, true);
    app(CreateManualPunches::class)($this->emp, '2026-09-08', '09:00', '18:00', 'came anyway', $this->hr);

    expect(AttendanceDay::where('employee_id', $this->emp->id)->whereDate('date', '2026-09-08')->value('flags'))->toContain('worked_on_leave');
    $this->actingAs($this->hr);
    Livewire::test(ListLeaveRequests::class)
        ->filterTable('status', RequestStatus::Approved->value)->filterTable('worked', true)
        ->assertCanSeeTableRecords([$leave])->assertSee('Punched on 1 day of this leave');
});
