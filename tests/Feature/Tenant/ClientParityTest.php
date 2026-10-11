<?php

declare(strict_types=1);

use App\Actions\Leave\SubmitLeaveRequest;
use App\Actions\Periods\PeriodActions;
use App\Actions\Punches\CreateManualPunches;
use App\Actions\Punches\CreateManualPunchRange;
use App\Actions\Rosters\GenerateMonthlyRoster;
use App\Enums\AttendanceStatus;
use App\Enums\ExemptionType;
use App\Enums\RequestStatus;
use App\Enums\ShiftOverrideScope;
use App\Models\AttendanceDay;
use App\Models\AttendanceExemption;
use App\Models\AttendancePeriod;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Reports\AttendanceSummaryReport;
use App\Reports\EmployeeInformationReport;
use App\Reports\ExemptionReport;
use App\Reports\MonthlyLeaveSummaryReport;
use App\Reports\YesterdayTodayReport;
use App\Services\Attendance\AttendanceRecomputer;
use App\Services\Attendance\Period\PayCycle;
use App\Services\Attendance\ShiftResolver;
use App\Services\Employees\EmployeeImporter;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use App\Services\Settings\TenantSettings;
use App\Support\Excel\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/*
| Features matching the client's legacy TMS screens: pay cycle (21st → 20th), October leave year, company-wide
| exemptions, late / half-day leave adjustment, grade-wise entitlements, date-range manual attendance,
| monthly roster generation and the legacy-style reports.
*/

function paritySetting(string $key, mixed $value): void
{
    Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    app(TenantSettings::class)->flush();
}

function parityStaff(string $code, array $attrs = []): Employee
{
    return Employee::create(array_merge(['code' => $code, 'first_name' => $code, 'joined_on' => '2025-01-01'], $attrs));
}

function parityDay(Employee $e, string $date, AttendanceStatus $status, array $extra = []): AttendanceDay
{
    return AttendanceDay::create(array_merge(['employee_id' => $e->id, 'date' => $date, 'status' => $status, 'flags' => []], $extra));
}

beforeEach(function () {
    $this->owner = User::where('email', 'owner@pest.test')->firstOrFail();
    $this->actingAs($this->owner);
});

it('runs the attendance month from the configured start day', function () {
    paritySetting('pay_cycle_start_day', 21);
    $cycle = app(PayCycle::class);

    expect($cycle->dates(2026, 6))->toBe(['2026-05-21', '2026-06-20'])
        ->and($cycle->dates(2027, 1))->toBe(['2026-12-21', '2027-01-20'])
        ->and($cycle->periodFor('2026-05-21'))->toBe([2026, 6])
        ->and($cycle->periodFor('2026-06-20'))->toBe([2026, 6])
        ->and($cycle->periodFor('2026-12-25'))->toBe([2027, 1]);

    paritySetting('pay_cycle_start_day', 1);
    expect(app(PayCycle::class)->dates(2026, 2))->toBe(['2026-02-01', '2026-02-28']);
});

it('locks the pay-cycle month rather than the calendar month', function () {
    paritySetting('pay_cycle_start_day', 21);
    $e = parityStaff('PC1');
    parityDay($e, '2026-05-25', AttendanceStatus::Absent);
    parityDay($e, '2026-06-10', AttendanceStatus::Absent);
    parityDay($e, '2026-06-25', AttendanceStatus::Absent);
    $this->travelTo(CarbonImmutable::parse('2026-07-01 10:00', 'Asia/Karachi'));

    $actions = app(PeriodActions::class);
    $period = $actions->lock($actions->period(2026, 6), $this->owner);

    expect(AttendancePeriod::isLocked('2026-05-25'))->toBeTrue()
        ->and(AttendancePeriod::isLocked('2026-06-25'))->toBeFalse()
        ->and($period->summary['employees'][$e->id]['absentDays'])->toBe(2)
        ->and(AttendanceDay::where('employee_id', $e->id)->whereDate('date', '2026-06-25')->value('is_locked'))->toBeFalsy();
});

it('charges lates and half days to leave when the month is locked and gives them back on unlock', function () {
    paritySetting('late_adjustment_mode', 'leave');
    paritySetting('late_adjustment_leave_type', 'CL');
    $cl = LeaveType::where('code', 'CL')->firstOrFail();
    $e = parityStaff('LA1');
    foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
        parityDay($e, $date, AttendanceStatus::Present, ['flags' => ['late'], 'late_min' => 20]);
    }
    parityDay($e, '2026-09-04', AttendanceStatus::HalfDay);
    $this->travelTo(CarbonImmutable::parse('2026-10-02 10:00', 'Asia/Karachi'));

    $actions = app(PeriodActions::class);
    $period = $actions->lock($actions->period(2026, 9), $this->owner);
    $balance = fn () => LeaveBalance::where(['employee_id' => $e->id, 'leave_type_id' => $cl->id, 'year' => 2026])->first();

    // 3 lates → 1 day, 1 half day → 0.5 day: all of it comes out of CL, nothing out of salary.
    expect($period->summary['employees'][$e->id]['leaveAdjustedDays'])->toBe(1.5)
        ->and($period->summary['employees'][$e->id]['deductionDays'])->toEqual(0)
        ->and($balance()->adjusted)->toBe(-1.5)
        ->and(LeaveAdjustment::where('attendance_period_id', $period->id)->value('days'))->toBe(-1.5);

    $actions->unlock($period->fresh(), $this->owner, 'recheck');

    expect($balance()->adjusted)->toBe(0.0)
        ->and(LeaveAdjustment::where('attendance_period_id', $period->id)->count())->toBe(0);
});

it('accrues monthly leave over a leave year that starts in October', function () {
    paritySetting('leave_year_start_month', 10);
    $type = LeaveType::create(['name' => 'Leaves', 'code' => 'LV', 'annual_quota_days' => 30, 'accrual' => 'monthly']);
    $veteran = parityStaff('LY1', ['joined_on' => '2020-01-01']);
    $joiner = parityStaff('LY2', ['joined_on' => '2026-03-10']);
    $leaveYear = app(LeaveYear::class);
    $balances = app(LeaveBalanceService::class);

    expect($leaveYear->yearFor('2026-09-30'))->toBe(2025)
        ->and($leaveYear->yearFor('2026-10-01'))->toBe(2026)
        ->and($leaveYear->label(2025))->toBe('2025-26')
        ->and($balances->accrued($veteran, $type, 2025, CarbonImmutable::parse('2026-05-15')))->toBe(20.0) // Oct → May: 8 × 2.5
        ->and($balances->accrued($veteran, $type, 2026, CarbonImmutable::parse('2026-10-01')))->toBe(2.5)
        ->and($balances->accrued($joiner, $type, 2025, CarbonImmutable::parse('2026-05-15')))->toBe(7.5); // Mar, Apr, May
});

it('takes the most specific grade / category entitlement and honours "earns leave"', function () {
    $al = LeaveType::where('code', 'AL')->firstOrFail(); // 14 days
    $officer = Grade::create(['code' => 'OFF', 'name' => 'Officer']);
    LeaveEntitlement::create(['leave_type_id' => $al->id, 'grade_id' => $officer->id, 'days_per_year' => 20]);
    LeaveEntitlement::create(['leave_type_id' => $al->id, 'employment_type' => 'contract', 'days_per_year' => 10]);
    LeaveEntitlement::create(['leave_type_id' => $al->id, 'grade_id' => $officer->id, 'employment_type' => 'contract', 'days_per_year' => 12]);
    $balances = app(LeaveBalanceService::class);

    expect($balances->entitlement(parityStaff('G1', ['grade_id' => $officer->id]), $al))->toBe(20.0)
        ->and($balances->entitlement(parityStaff('G2', ['grade_id' => $officer->id, 'employment_type' => 'contract']), $al))->toBe(12.0)
        ->and($balances->entitlement(parityStaff('G3', ['employment_type' => 'contract']), $al))->toBe(10.0)
        ->and($balances->entitlement(parityStaff('G4'), $al))->toBe(14.0)
        ->and($balances->accrued(parityStaff('G5', ['leave_credit' => false]), $al, 2026))->toBe(0.0);
});

it('lets a negative-balance leave type go overdrawn but not across the leave-year end', function () {
    paritySetting('leave_year_start_month', 10);
    $type = LeaveType::create(['name' => 'Leaves', 'code' => 'LV', 'annual_quota_days' => 2, 'allow_negative_balance' => true]);
    $e = parityStaff('NB1');
    $submit = app(SubmitLeaveRequest::class);

    expect($submit($e, $type, '2026-09-07', '2026-09-11', $this->owner)->days)->toBe(5.0)
        ->and(fn () => $submit($e, $type, '2026-09-28', '2026-10-02', $this->owner))->toThrow(ValidationException::class);
});

it('excuses absences on an exempted day only for the employees in scope', function () {
    $production = Department::create(['name' => 'Production', 'code' => 'PRD']);
    $office = Department::create(['name' => 'Office', 'code' => 'OFC']);
    $worker = parityStaff('EX1', ['department_id' => $production->id]);
    $clerk = parityStaff('EX2', ['department_id' => $office->id]);
    AttendanceExemption::create(['date' => '2026-09-01', 'type' => ExemptionType::Absent, 'reason' => 'Strike',
        'scope' => ShiftOverrideScope::Department, 'scope_id' => $production->id]);
    $now = CarbonImmutable::parse('2026-09-20 12:00', 'Asia/Karachi')->utc();
    $recompute = fn (Employee $e) => app(AttendanceRecomputer::class)->recompute($e->id, '2026-09-01', $now)?->status;

    expect($recompute($worker))->toBe(AttendanceStatus::Exempted)
        ->and($recompute($clerk))->toBe(AttendanceStatus::Absent);
});

it('enters the same manual times over a date range and removes them again', function () {
    Holiday::query()->delete();
    $this->travelTo(CarbonImmutable::parse('2026-09-20 12:00', 'Asia/Karachi'));
    $e = parityStaff('MR1');
    app(CreateManualPunches::class)($e, '2026-09-02', '08:00', null, 'came early', $this->owner);
    $range = app(CreateManualPunchRange::class);

    // Mon 31 Aug → Sun 6 Sep: 2 Sep already has a punch, Sunday is the weekly off.
    $result = $range($e, '2026-08-31', '2026-09-06', '09:00', '18:00', 'Machine down', $this->owner);

    expect($result['saved'])->toBe(['2026-08-31', '2026-09-01', '2026-09-03', '2026-09-04', '2026-09-05'])
        ->and(array_keys($result['skipped']))->toBe(['2026-09-02', '2026-09-06'])
        ->and(Punch::where('employee_id', $e->id)->count())->toBe(11);

    expect($range->remove($e, '2026-08-31', '2026-09-03', 'entered by mistake', $this->owner))->toBe(7)
        ->and(Punch::where('employee_id', $e->id)->count())->toBe(4);
});

it('rosters a department onto a shift for a whole pay-cycle month', function () {
    paritySetting('pay_cycle_start_day', 21);
    $ops = Department::create(['name' => 'Operations', 'code' => 'OPS']);
    $night = Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '20:00', 'end_time' => '04:00', 'crosses_midnight' => true]);
    $onRoster = parityStaff('RO1', ['department_id' => $ops->id]);
    $other = parityStaff('RO2');
    $generate = app(GenerateMonthlyRoster::class);

    expect($generate(2026, 11, $night, 'department', $ops->id, $this->owner))->toBe(1);
    $generate(2026, 11, $night, 'department', $ops->id, $this->owner); // running it again replaces, not stacks

    $rows = ShiftAssignment::where('employee_id', $onRoster->id)->get();
    $resolver = app(ShiftResolver::class);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->effective_from->format('Y-m-d'))->toBe('2026-10-21')
        ->and($rows[0]->effective_to->format('Y-m-d'))->toBe('2026-11-20')
        ->and(ShiftAssignment::where('employee_id', $other->id)->count())->toBe(0)
        ->and($resolver->resolve($onRoster, '2026-11-05', 'Asia/Karachi')?->shiftId)->toBe($night->id)
        ->and($resolver->resolve($onRoster, '2026-11-21', 'Asia/Karachi')?->shiftId)->not->toBe($night->id);
});

it('summarises a pay-cycle month in the legacy attendance summary layout', function () {
    paritySetting('pay_cycle_start_day', 21);
    $e = parityStaff('AS1');
    parityDay($e, '2026-05-21', AttendanceStatus::Present);
    parityDay($e, '2026-05-22', AttendanceStatus::Present, ['flags' => ['late']]);
    parityDay($e, '2026-05-24', AttendanceStatus::WeeklyOff);
    parityDay($e, '2026-06-20', AttendanceStatus::Absent);
    parityDay($e, '2026-06-21', AttendanceStatus::Absent); // next month

    $row = collect((new AttendanceSummaryReport)->rows(['month' => '2026-06']))->firstWhere('code', 'AS1');

    expect($row['on_time'])->toBe(1)->and($row['late'])->toBe(1)->and($row['absent'])->toBe(1)
        ->and($row['off'])->toBe(1)->and($row['total'])->toBe(4);
});

it('rolls a leave balance forward month by month in the leave summary', function () {
    paritySetting('leave_year_start_month', 10);
    $type = LeaveType::create(['name' => 'Leaves', 'code' => 'LV', 'annual_quota_days' => 30, 'accrual' => 'monthly', 'allow_negative_balance' => true]);
    $e = parityStaff('LS1', ['joined_on' => '2020-01-01', 'payroll_id' => '320001']);
    LeaveBalance::create(['employee_id' => $e->id, 'leave_type_id' => $type->id, 'year' => 2025, 'opening' => 28.5]);
    $leave = fn (string $from, string $to, float $days) => LeaveRequest::create(['employee_id' => $e->id, 'leave_type_id' => $type->id,
        'from_date' => $from, 'to_date' => $to, 'days' => $days, 'status' => RequestStatus::Approved]);
    $leave('2026-04-14', '2026-04-14', 1);
    $leave('2026-05-12', '2026-05-13', 2);
    $adjust = fn (float $days, string $on, ?int $periodId = null) => LeaveAdjustment::create(['employee_id' => $e->id, 'leave_type_id' => $type->id,
        'year' => 2025, 'days' => $days, 'effective_on' => $on, 'reason' => 'test', 'attendance_period_id' => $periodId]);
    $adjust(-1, '2026-05-31', app(PeriodActions::class)->period(2026, 5)->id); // lates / half days, charged at lock
    $adjust(1, '2026-05-20');
    $adjust(-0.5, '2026-05-21'); // HR's own correction: not a late / half-day adjustment
    $unpaid = LeaveType::create(['name' => 'Without pay', 'code' => 'UL', 'annual_quota_days' => 0, 'is_paid' => false, 'allow_negative_balance' => true]);
    $unpaidLeave = LeaveRequest::create(['employee_id' => $e->id, 'leave_type_id' => $unpaid->id,
        'from_date' => '2026-05-14', 'to_date' => '2026-05-14', 'days' => 1, 'status' => RequestStatus::Approved]);
    AttendanceDay::updateOrCreate(['employee_id' => $e->id, 'date' => '2026-05-14'], ['status' => AttendanceStatus::LeaveUnpaid, 'flags' => [], 'leave_request_id' => $unpaidLeave->id]);
    $summary = fn (LeaveType $t) => collect((new MonthlyLeaveSummaryReport)->rows(['month' => '2026-05', 'leave_type_id' => $t->id]))->firstWhere('code', 'LS1');

    $row = $summary($type);

    // Before May: 28.5 opening + 7 months × 2.5 (Oct → Apr) − 1 used = 45
    expect($row['payroll_id'])->toBe('320001')
        ->and($row['previous'])->toBe(45.0)->and($row['allotted'])->toBe(2.5)->and($row['availed'])->toBe(2.0)
        ->and($row['adjusted'])->toBe(1.0)->and($row['credit'])->toBe(0.5)->and($row['lwp'])->toBeNull()
        ->and($row['closing'])->toBe(45.0)
        ->and($summary($unpaid)['lwp'])->toBe(1);
});

it('shows yesterday out next to today in', function () {
    $e = parityStaff('YT1');
    parityDay($e, '2026-09-01', AttendanceStatus::Present, ['last_out' => '2026-09-01 15:51:00']); // 20:51 PKT
    parityDay($e, '2026-09-02', AttendanceStatus::Present, ['first_in' => '2026-09-02 07:09:00']); // 12:09 PKT

    $row = collect((new YesterdayTodayReport)->rows(['date' => '2026-09-02']))->firstWhere('code', 'YT1');

    expect($row['yesterday_out'])->toBe('20:51')->and($row['today_in'])->toBe('12:09')->and($row['status'])->toBe('Present');
});

it('filters reports by designation, category and shift', function () {
    $drivers = Designation::create(['name' => 'Driver']);
    $night = Shift::create(['name' => 'Night', 'code' => 'N', 'start_time' => '20:00', 'end_time' => '04:00', 'crosses_midnight' => true]);
    $default = Shift::where('is_default', true)->firstOrFail();
    parityStaff('F1', ['designation_id' => $drivers->id, 'cnic' => '4240119007349', 'father_name' => 'Abdul Hafeez']);
    parityStaff('F2', ['employment_type' => 'contract']);
    $nightWorker = parityStaff('F3');
    ShiftAssignment::create(['employee_id' => $nightWorker->id, 'shift_id' => $night->id, 'effective_from' => '2026-01-01']);
    $report = new EmployeeInformationReport;
    $codes = fn (array $filters) => collect($report->rows($filters))->pluck('code')->all();

    expect($codes(['designation_id' => $drivers->id]))->toBe(['F1'])
        ->and($codes(['employment_type' => 'contract']))->toBe(['F2'])
        ->and($codes(['shift_id' => $night->id]))->toBe(['F3'])
        ->and($codes(['shift_id' => $default->id]))->toContain('F1')->not->toContain('F3')
        ->and($report->rows(['designation_id' => $drivers->id])[0])->toMatchArray(['cnic' => '42401-1900734-9', 'father' => 'Abdul Hafeez']);
});

it('imports the extra employee master fields and creates missing grades', function () {
    $path = tempnam(sys_get_temp_dir(), 'emp') . '.xlsx';
    $row = array_fill_keys(EmployeeImporter::HEADERS, '');
    $row = array_merge($row, ['code' => 'IM1', 'first_name' => 'Imran', 'joined_on' => '2024-01-15', 'father_name' => 'Abdul Hafeez',
        'address' => 'House 10, North Karachi', 'city' => 'Karachi', 'grade' => 'Officer', 'payroll_id' => '320005']);
    Spreadsheet::write($path, EmployeeImporter::HEADERS, [array_values($row)]);
    $importer = app(EmployeeImporter::class);

    $preview = $importer->preview($path);
    $importer->import($preview['rows']);
    $e = Employee::where('code', 'IM1')->with('grade')->firstOrFail();

    expect($preview['errors'])->toBe([])
        ->and($e->father_name)->toBe('Abdul Hafeez')->and($e->city)->toBe('Karachi')->and($e->payroll_id)->toBe('320005')
        ->and($e->grade?->name)->toBe('Officer');
});

it('narrows any report to one employee', function () {
    parityStaff('ONE1');
    $two = parityStaff('ONE2');

    expect(collect((new EmployeeInformationReport)->rows(['employee_id' => $two->id]))->pluck('code')->all())->toBe(['ONE2']);
});

it('lists exemptions with who they apply to', function () {
    $ops = Department::create(['name' => 'Operations', 'code' => 'OPX']);
    AttendanceExemption::create(['date' => '2026-07-20', 'type' => ExemptionType::Late, 'reason' => 'Heavy rain', 'created_by' => $this->owner->id]);
    AttendanceExemption::create(['date' => '2026-07-21', 'type' => ExemptionType::Absent, 'reason' => 'Strike',
        'scope' => ShiftOverrideScope::Department, 'scope_id' => $ops->id]);
    AttendanceExemption::create(['date' => '2026-09-01', 'type' => ExemptionType::Late, 'reason' => 'Outside range']);

    $rows = (new ExemptionReport)->rows(['from' => '2026-07-01', 'to' => '2026-07-31']);

    expect(array_column($rows, 'reason'))->toBe(['Heavy rain', 'Strike'])
        ->and($rows[0])->toMatchArray(['date' => '20 Jul 2026', 'day' => 'Monday', 'scope' => 'Whole company', 'by' => $this->owner->name])
        ->and($rows[1]['scope'])->toBe('Department: Operations');
});
