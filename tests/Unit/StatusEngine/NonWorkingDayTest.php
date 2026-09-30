<?php

declare(strict_types=1);

use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveDayPart;
use App\Services\Attendance\Engine\EngineSettings;
use App\Services\Attendance\Engine\LeaveInput;
use App\Services\Attendance\Engine\StatusEngine;
use Tests\Support\Scenario;

beforeEach(fn () => $this->engine = new StatusEngine);

it('returns NOT_JOINED before the joining date and LEFT after leaving', function () {
    $before = $this->engine->resolve(Scenario::day('2026-09-01', [], ['joinedOn' => '2026-09-02']));
    $joinDay = $this->engine->resolve(Scenario::day('2026-09-02', [], ['joinedOn' => '2026-09-02']));
    $lastDay = $this->engine->resolve(Scenario::day('2026-09-10', [], ['leftOn' => '2026-09-10']));
    $after = $this->engine->resolve(Scenario::day('2026-09-11', [], ['leftOn' => '2026-09-10']));

    expect($before->status)->toBe(AttendanceStatus::NotJoined)
        ->and($joinDay->status)->toBe(AttendanceStatus::Absent)
        ->and($lastDay->status)->toBe(AttendanceStatus::Absent)
        ->and($after->status)->toBe(AttendanceStatus::Left);
});

it('marks a holiday and still computes work and overtime when punched', function () {
    $ctx = Scenario::day('2026-09-01', ['2026-09-01 10:00', '2026-09-01 14:00'], [
        'isHoliday' => true,
        'holidayId' => 7,
        'overtimeEligible' => true,
        'settings' => new EngineSettings(overtimeEnabled: true),
    ]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::Holiday)
        ->and($r->holidayId)->toBe(7)
        ->and($r->workedMin)->toBe(180)
        ->and($r->overtimeMin)->toBe(180)
        ->and($r->hasFlag(AttendanceFlag::Late))->toBeFalse();
});

it('marks a holiday without punches', function () {
    expect($this->engine->resolve(Scenario::day('2026-09-01', [], ['isHoliday' => true]))->status)
        ->toBe(AttendanceStatus::Holiday);
});

it('marks the shift weekly off day', function () {
    expect($this->engine->resolve(Scenario::day('2026-09-06'))->status)->toBe(AttendanceStatus::WeeklyOff);
});

it('uses shift-specific weekly offs', function () {
    $ctx = Scenario::day('2026-09-05', [], ['shift' => Scenario::shift(['weeklyOffDays' => [0, 6]])]);

    expect($this->engine->resolve($ctx)->status)->toBe(AttendanceStatus::WeeklyOff);
});

it('marks full-day paid and unpaid leave', function () {
    $paid = $this->engine->resolve(Scenario::day('2026-09-01', [], ['leave' => new LeaveInput(11, isPaid: true)]));
    $unpaid = $this->engine->resolve(Scenario::day('2026-09-01', [], ['leave' => new LeaveInput(12, isPaid: false)]));

    expect($paid->status)->toBe(AttendanceStatus::LeavePaid)
        ->and($paid->leaveRequestId)->toBe(11)
        ->and($unpaid->status)->toBe(AttendanceStatus::LeaveUnpaid);
});

it('resolves half-day leave with punches without flagging the leave half as late', function () {
    $ctx = Scenario::day('2026-09-01', ['2026-09-01 13:30', '2026-09-01 18:00'], [
        'leave' => new LeaveInput(13, isPaid: true, dayPart: LeaveDayPart::FirstHalf),
    ]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::HalfDayLeave)
        ->and($r->hasFlag(AttendanceFlag::Late))->toBeFalse()
        ->and($r->hasFlag(AttendanceFlag::MissingIn))->toBeFalse();
});

it('resolves half-day leave without punches as leave + missing_in', function () {
    $ctx = Scenario::day('2026-09-01', [], [
        'leave' => new LeaveInput(13, isPaid: true, dayPart: LeaveDayPart::SecondHalf),
    ]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::HalfDayLeave)
        ->and($r->hasFlag(AttendanceFlag::MissingIn))->toBeTrue();
});

it('marks official duty and work from home', function (AttendanceStatus $marker) {
    expect($this->engine->resolve(Scenario::day('2026-09-01', [], ['marker' => $marker]))->status)->toBe($marker);
})->with([AttendanceStatus::OfficialDuty, AttendanceStatus::Wfh]);

it('turns a sandwiched weekly off into leave', function () {
    $ctx = Scenario::day('2026-09-06', [], ['leave' => new LeaveInput(14, isPaid: true, sandwich: true)]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::LeavePaid)
        ->and($r->hasFlag(AttendanceFlag::SandwichApplied))->toBeTrue();
});

it('treats short leave as a flag only, judging the day on its punches', function () {
    $ctx = Scenario::day('2026-09-01', ['2026-09-01 09:00', '2026-09-01 18:00'], [
        'leave' => new LeaveInput(16, isPaid: true, isShortLeave: true),
    ]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->hasFlag(AttendanceFlag::ShortLeave))->toBeTrue()
        ->and($r->leaveRequestId)->toBe(16);
});

it('lets a holiday win over non-sandwich leave', function () {
    $ctx = Scenario::day('2026-09-01', [], ['isHoliday' => true, 'leave' => new LeaveInput(15, isPaid: true)]);

    expect($this->engine->resolve($ctx)->status)->toBe(AttendanceStatus::Holiday);
});
