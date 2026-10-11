<?php

declare(strict_types=1);

use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus;
use App\Enums\MultiPunchMode;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Services\Attendance\Engine\EngineSettings;
use App\Services\Attendance\Engine\StatusEngine;
use Tests\Support\Scenario;

beforeEach(fn () => $this->engine = new StatusEngine);

it('marks an on-time day present with no flags', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 08:55', '2026-09-01 18:05']));

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->flags)->toBe([])
        ->and($r->lateMin)->toBe(0)
        ->and($r->earlyOutMin)->toBe(0)
        ->and($r->workedMin)->toBe(490) // 550 span − 60 break
        ->and($r->expectedMin)->toBe(480)
        ->and($r->firstIn->equalTo(Scenario::at('2026-09-01 08:55')))->toBeTrue()
        ->and($r->sourceSummary)->toBe(['agent' => 2]);
});

it('does not flag arrivals within grace as late', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 09:14', '2026-09-01 18:00']));

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->hasFlag(AttendanceFlag::Late))->toBeFalse()
        ->and($r->lateMin)->toBe(0);
});

it('flags late beyond grace and counts minutes after grace', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 09:40', '2026-09-01 18:00']));

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->hasFlag(AttendanceFlag::Late))->toBeTrue()
        ->and($r->lateMin)->toBe(25);
});

it('respects late_after_min when it differs from grace', function () {
    $ctx = Scenario::day('2026-09-01', ['2026-09-01 09:25', '2026-09-01 18:00'], [
        'shift' => Scenario::shift(['lateAfterMin' => 30]),
    ]);

    expect($this->engine->resolve($ctx)->hasFlag(AttendanceFlag::Late))->toBeFalse();
});

it('turns a very late arrival into a half day', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 11:30', '2026-09-01 18:00']));

    expect($r->status)->toBe(AttendanceStatus::HalfDay)
        ->and($r->hasFlag(AttendanceFlag::Late))->toBeTrue()
        ->and($r->lateMin)->toBe(135);
});

it('turns a short worked day into a half day and flags early out', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 09:00', '2026-09-01 12:30']));

    expect($r->status)->toBe(AttendanceStatus::HalfDay)
        ->and($r->workedMin)->toBe(150)
        ->and($r->hasFlag(AttendanceFlag::EarlyOut))->toBeTrue()
        ->and($r->earlyOutMin)->toBe(325); // 18:00 − 5 grace − 12:30
});

it('counts an early out only beyond early_out_before_min', function () {
    $within = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 09:00', '2026-09-01 17:40']));
    $beyond = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 09:00', '2026-09-01 17:20']));

    expect($within->hasFlag(AttendanceFlag::EarlyOut))->toBeFalse()
        ->and($within->earlyOutMin)->toBe(0)
        ->and($beyond->hasFlag(AttendanceFlag::EarlyOut))->toBeTrue()
        ->and($beyond->earlyOutMin)->toBe(35); // 40 early − 5 grace
});

it('falls back to the out grace when early_out_before_min is 0', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 09:00', '2026-09-01 17:50'], [
        'shift' => Scenario::shift(['earlyOutBeforeMin' => 0]),
    ]));

    expect($r->hasFlag(AttendanceFlag::EarlyOut))->toBeTrue()
        ->and($r->earlyOutMin)->toBe(5);
});

it('keeps a single punch present with missing_out and no worked time', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 09:00']));

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->hasFlag(AttendanceFlag::MissingOut))->toBeTrue()
        ->and($r->hasFlag(AttendanceFlag::AutoClosed))->toBeFalse()
        ->and($r->lastOut)->toBeNull()
        ->and($r->workedMin)->toBe(0);
});

it('auto-closes a missing out once the configured time has passed', function () {
    $ctx = Scenario::day('2026-09-01', ['2026-09-01 09:00'], [
        'shift' => Scenario::shift(['autoCloseMissingOutAfterMin' => 720]),
    ]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->hasFlag(AttendanceFlag::AutoClosed))->toBeTrue()
        ->and($r->hasFlag(AttendanceFlag::MissingOut))->toBeTrue()
        ->and($r->lastOut->equalTo(Scenario::at('2026-09-01 18:00')))->toBeTrue()
        ->and($r->workedMin)->toBe(480)
        ->and($r->hasFlag(AttendanceFlag::EarlyOut))->toBeFalse();
});

it('does not auto-close before the configured time', function () {
    $ctx = Scenario::day('2026-09-01', ['2026-09-01 09:00'], [
        'shift' => Scenario::shift(['autoCloseMissingOutAfterMin' => 720]),
        'now' => Scenario::at('2026-09-01 15:00'),
    ]);

    expect($this->engine->resolve($ctx)->hasFlag(AttendanceFlag::AutoClosed))->toBeFalse();
});

it('flags a lone out punch as missing_in when directions are trusted', function () {
    $ctx = Scenario::day('2026-09-01', [], [
        'punches' => [Scenario::punch('2026-09-01 18:00', PunchDirection::Out)],
    ]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->hasFlag(AttendanceFlag::MissingIn))->toBeTrue()
        ->and($r->firstIn)->toBeNull();
});

it('resolves a night shift whose punches span two dates', function () {
    $shift = Scenario::shift(['startTime' => '22:00', 'endTime' => '06:00', 'crossesMidnight' => true, 'breakMin' => 0]);
    $punches = ['2026-09-01 21:55', '2026-09-02 06:05'];

    $night = $this->engine->resolve(Scenario::day('2026-09-01', $punches, ['shift' => $shift]));
    $nextDay = $this->engine->resolve(Scenario::day('2026-09-02', $punches, ['shift' => $shift]));

    expect($night->status)->toBe(AttendanceStatus::Present)
        ->and($night->workedMin)->toBe(490)
        ->and($night->expectedMin)->toBe(480)
        ->and($night->hasFlag(AttendanceFlag::Late))->toBeFalse()
        // The 06:05 punch belongs to the previous night, not to the 2 Sept shift.
        ->and($nextDay->status)->toBe(AttendanceStatus::Absent);
});

it('ignores double taps within the dedupe window', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', [
        '2026-09-01 09:00:00', '2026-09-01 09:00:30', '2026-09-01 18:00:00',
    ]));

    expect($r->sourceSummary)->toBe(['agent' => 2])
        ->and($r->workedMin)->toBe(480);
});

it('pairs punches in paired mode and does not double-deduct a punched-out break', function () {
    $ctx = Scenario::day('2026-09-01', [
        '2026-09-01 09:00', '2026-09-01 09:00:20', // double tap, dropped
        '2026-09-01 13:00', '2026-09-01 14:00', '2026-09-01 18:00',
    ], ['shift' => Scenario::shift(['multiPunchMode' => MultiPunchMode::Paired])]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->workedMin)->toBe(480)
        ->and($r->hasFlag(AttendanceFlag::MissingOut))->toBeFalse();
});

it('flags missing_out on an odd punch count in paired mode', function () {
    $ctx = Scenario::day('2026-09-01', ['2026-09-01 09:00', '2026-09-01 13:00', '2026-09-01 14:00'], [
        'shift' => Scenario::shift(['multiPunchMode' => MultiPunchMode::Paired]),
    ]);
    $r = $this->engine->resolve($ctx);

    expect($r->hasFlag(AttendanceFlag::MissingOut))->toBeTrue()
        ->and($r->status)->toBe(AttendanceStatus::Present);
});

it('marks a past working day with no punches absent', function () {
    expect($this->engine->resolve(Scenario::day('2026-09-01'))->status)->toBe(AttendanceStatus::Absent);
});

it('leaves today pending when there are no punches yet', function () {
    $ctx = Scenario::day('2026-09-01', [], ['now' => Scenario::at('2026-09-01 11:00')]);

    expect($this->engine->resolve($ctx)->status)->toBeNull();
});

it('computes overtime only for eligible employees, rounded down and capped', function () {
    $settings = new EngineSettings(overtimeEnabled: true, overtimeRoundingMin: 15);
    $punches = ['2026-09-01 09:00', '2026-09-01 20:10']; // 610 worked, 130 extra
    $shift = Scenario::shift(['overtimeAfterMin' => 30, 'maxOvertimeMin' => 120]);

    $eligible = $this->engine->resolve(Scenario::day('2026-09-01', $punches, [
        'settings' => $settings, 'overtimeEligible' => true, 'shift' => $shift,
    ]));
    $notEligible = $this->engine->resolve(Scenario::day('2026-09-01', $punches, [
        'settings' => $settings, 'overtimeEligible' => false, 'shift' => $shift,
    ]));

    expect($eligible->overtimeMin)->toBe(120)
        ->and($eligible->hasFlag(AttendanceFlag::Overtime))->toBeTrue()
        ->and($notEligible->overtimeMin)->toBe(0);
});

it('flags manual punches and records sources', function () {
    $ctx = Scenario::day('2026-09-01', [], ['punches' => [
        Scenario::punch('2026-09-01 09:00', source: PunchSource::Manual, manual: true),
        Scenario::punch('2026-09-01 18:00', source: PunchSource::Kiosk),
    ]]);
    $r = $this->engine->resolve($ctx);

    expect($r->hasFlag(AttendanceFlag::ManualPunch))->toBeTrue()
        ->and($r->sourceSummary)->toBe(['manual' => 1, 'kiosk' => 1]);
});

it('applies a manual override but keeps the computed numbers', function () {
    $ctx = Scenario::day('2026-09-01', ['2026-09-01 11:30', '2026-09-01 18:00'], [
        'manualOverride' => AttendanceStatus::Present,
    ]);
    $r = $this->engine->resolve($ctx);

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->hasFlag(AttendanceFlag::ManualOverride))->toBeTrue()
        ->and($r->lateMin)->toBe(135);
});
