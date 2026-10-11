<?php

declare(strict_types=1);

use App\Actions\Punches\CreateManualPunches;
use App\Actions\Punches\IngestOutcome;
use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\IngestResult;
use App\Actions\Punches\MapUnmappedPinAction;
use App\Actions\Punches\PunchData;
use App\Enums\AttendanceStatus;
use App\Enums\PeriodStatus;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\Role;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\UnmappedPin;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

function machinePunch(string $pin, string $local, array $extra = []): PunchData
{
    return new PunchData(...array_merge([
        'punchedAt' => CarbonImmutable::parse($local, 'Asia/Karachi')->utc(),
        'source' => PunchSource::Agent,
        'devicePin' => $pin,
        'deviceSerial' => 'ZK1234567',
        'direction' => PunchDirection::In,
    ], $extra));
}

function ingest(PunchData $d): IngestResult
{
    return app(IngestPunchAction::class)($d);
}

function staff(string $pin, string $code): Employee
{
    return Employee::create(['code' => $code, 'first_name' => $code, 'joined_on' => '2025-01-01', 'device_pin' => $pin]);
}

it('stores a machine punch, maps the PIN and recomputes the day', function () {
    $e = staff('47', 'ALI');

    $r1 = ingest(machinePunch('47', '2026-09-01 09:02'));
    ingest(machinePunch('47', '2026-09-01 18:01'));

    expect($r1->outcome)->toBe(IngestOutcome::Stored)
        ->and($r1->punch->employee_id)->toBe($e->id)
        ->and($r1->punch->local_date->format('Y-m-d'))->toBe('2026-09-01')
        ->and($r1->punch->direction)->toBe(PunchDirection::Unknown) // trust_device_direction = false
        ->and(AttendanceDay::where('employee_id', $e->id)->whereDate('date', '2026-09-01')->value('status'))
        ->toBe(AttendanceStatus::Present);
});

it('ignores an exact replay of the same punch', function () {
    staff('47', 'ALI');
    ingest(machinePunch('47', '2026-09-01 09:02'));

    expect(ingest(machinePunch('47', '2026-09-01 09:02'))->outcome)->toBe(IngestOutcome::AlreadyStored)
        ->and(Punch::count())->toBe(1);
});

it('marks a second tap within the dedupe window as duplicate', function () {
    staff('47', 'ALI');
    ingest(machinePunch('47', '2026-09-01 09:02:00'));
    $tap = ingest(machinePunch('47', '2026-09-01 09:02:40'));

    expect($tap->outcome)->toBe(IngestOutcome::DoubleTap)->and($tap->punch->status)->toBe(PunchStatus::Duplicate);
});

it('stores unknown PINs as unmapped and lists them', function () {
    $r = ingest(machinePunch('99', '2026-09-01 09:00'));
    ingest(machinePunch('99', '2026-09-01 18:00'));

    expect($r->outcome)->toBe(IngestOutcome::Unmapped)
        ->and($r->punch->employee_id)->toBeNull()
        ->and(UnmappedPin::where('device_pin', '99')->value('count'))->toBe(2);
});

it('re-attaches punches when an unmapped PIN is mapped', function () {
    ingest(machinePunch('99', '2026-09-01 09:00'));
    ingest(machinePunch('99', '2026-09-01 18:00'));
    $e = Employee::create(['code' => 'NEW', 'first_name' => 'New', 'joined_on' => '2025-01-01']);

    $result = app(MapUnmappedPinAction::class)(UnmappedPin::where('device_pin', '99')->firstOrFail(), $e);

    expect($result)->toBe(['attached' => 2, 'locked_dates' => []])
        ->and($e->fresh()->device_pin)->toBe('99')
        ->and(Punch::where('employee_id', $e->id)->where('status', PunchStatus::Valid)->count())->toBe(2)
        ->and(UnmappedPin::where('device_pin', '99')->value('resolved_employee_id'))->toBe($e->id)
        ->and(AttendanceDay::where('employee_id', $e->id)->whereDate('date', '2026-09-01')->value('status'))
        ->toBe(AttendanceStatus::Present);
});

it('attaches waiting punches when an employee gets that Machine PIN on the profile', function () {
    ingest(machinePunch('77', '2026-09-01 09:00'));
    ingest(machinePunch('88', '2026-09-01 09:05'));
    $changed = staff('12', 'CHG');

    $changed->update(['device_pin' => '77']);
    $created = Employee::create(['code' => 'NEW', 'first_name' => 'New', 'joined_on' => '2025-01-01', 'device_pin' => '88']);

    expect(Punch::where('device_pin', '77')->value('employee_id'))->toBe($changed->id)
        ->and(Punch::where('device_pin', '88')->value('status'))->toBe(PunchStatus::Valid)
        ->and(Punch::where('device_pin', '88')->value('employee_id'))->toBe($created->id)
        ->and(UnmappedPin::where('device_pin', '77')->value('resolved_employee_id'))->toBe($changed->id)
        ->and(AttendanceDay::where('employee_id', $changed->id)->whereDate('date', '2026-09-01')->exists())->toBeTrue();
});

it('says which mapped days were left alone because their month is locked', function () {
    ingest(machinePunch('99', '2026-09-01 09:00'));
    ingest(machinePunch('99', '2026-10-02 09:00'));
    AttendancePeriod::create(['year' => 2026, 'month' => 9, 'status' => PeriodStatus::Locked]);
    $e = Employee::create(['code' => 'NEW', 'first_name' => 'New', 'joined_on' => '2025-01-01']);

    $result = app(MapUnmappedPinAction::class)(UnmappedPin::where('device_pin', '99')->firstOrFail(), $e);

    expect($result)->toBe(['attached' => 2, 'locked_dates' => ['2026-09-01']])
        ->and(Punch::where('employee_id', $e->id)->count())->toBe(2);
});

it('refuses to map a PIN onto an employee who already has another PIN', function () {
    ingest(machinePunch('99', '2026-09-01 09:00'));
    $e = staff('12', 'HAS');

    app(MapUnmappedPinAction::class)(UnmappedPin::firstOrFail(), $e);
})->throws(ValidationException::class);

it('creates manual in/out punches with reason and handles night shifts', function () {
    $e = staff('50', 'NGT');
    $hr = User::create(['name' => 'HR', 'email' => 'hr@pest.test', 'password' => 'x']);
    $hr->assignRole(Role::Hr->value);

    $results = app(CreateManualPunches::class)($e, '2026-09-01', '22:00', '06:00', 'Machine down', $hr);

    expect($results)->toHaveCount(2)
        ->and($results[1]->punch->local_date->format('Y-m-d'))->toBe('2026-09-02')
        ->and($results[0]->punch->is_manual)->toBeTrue()
        ->and($results[0]->punch->reason)->toBe('Machine down')
        ->and($results[0]->punch->entered_by_user_id)->toBe($hr->id);
});

it('limits receptionists to today and yesterday', function () {
    $e = staff('51', 'REC');
    $rec = User::create(['name' => 'Rec', 'email' => 'rec@pest.test', 'password' => 'x']);
    $rec->assignRole(Role::Receptionist->value);

    app(CreateManualPunches::class)($e, now('Asia/Karachi')->subDays(5)->format('Y-m-d'), '09:00', null, 'late entry', $rec);
})->throws(ValidationException::class);

it('requires a reason for manual punches', function () {
    $e = staff('52', 'RSN');
    $hr = User::create(['name' => 'HR2', 'email' => 'hr2@pest.test', 'password' => 'x']);
    $hr->assignRole(Role::Hr->value);

    app(CreateManualPunches::class)($e, '2026-09-01', '09:00', null, '  ', $hr);
})->throws(ValidationException::class);
