<?php

declare(strict_types=1);

use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus;
use App\Services\Attendance\Engine\ShiftOverrideApplier;
use App\Services\Attendance\Engine\ShiftOverrideInput;
use App\Services\Attendance\Engine\StatusEngine;
use Tests\Support\Scenario;

it('applies Ramadan timings so a 09:00–15:00 day is a full present day', function () {
    $ramadan = new ShiftOverrideInput(id: 3, priority: 10, changes: [
        'endTime' => '15:00', 'breakMin' => 0, 'halfDayIfWorkedLessThanMin' => 180,
    ]);
    $shift = (new ShiftOverrideApplier)->apply(Scenario::shift(), '2026-09-01', Scenario::TZ, [$ramadan]);
    $punches = ['2026-09-01 09:00', '2026-09-01 15:00'];

    $withOverride = (new StatusEngine)->resolve(Scenario::day('2026-09-01', $punches, ['shift' => $shift]));
    $without = (new StatusEngine)->resolve(Scenario::day('2026-09-01', $punches));

    expect($withOverride->status)->toBe(AttendanceStatus::Present)
        ->and($withOverride->hasFlag(AttendanceFlag::EarlyOut))->toBeFalse()
        ->and($withOverride->expectedMin)->toBe(360)
        ->and($withOverride->overrideId)->toBe(3)
        ->and($without->hasFlag(AttendanceFlag::EarlyOut))->toBeTrue();
});

it('applies a Jummah override only on Fridays', function () {
    $jummah = new ShiftOverrideInput(id: 4, priority: 5, changes: ['breakMin' => 120], weekday: 5);
    $applier = new ShiftOverrideApplier;

    expect($applier->apply(Scenario::shift(), '2026-09-04', Scenario::TZ, [$jummah])->breakMin)->toBe(120)
        ->and($applier->apply(Scenario::shift(), '2026-09-03', Scenario::TZ, [$jummah])->breakMin)->toBe(60);
});

it('lets the highest priority override win', function () {
    $low = new ShiftOverrideInput(id: 1, priority: 1, changes: ['graceInMin' => 30]);
    $high = new ShiftOverrideInput(id: 2, priority: 9, changes: ['graceInMin' => 45]);

    $shift = (new ShiftOverrideApplier)->apply(Scenario::shift(), '2026-09-01', Scenario::TZ, [$high, $low]);

    expect($shift->graceInMin)->toBe(45)->and($shift->overrideId)->toBe(2);
});

it('ignores unknown override keys', function () {
    $o = new ShiftOverrideInput(id: 1, priority: 1, changes: ['shiftId' => 999, 'graceInMin' => 20]);

    $shift = (new ShiftOverrideApplier)->apply(Scenario::shift(), '2026-09-01', Scenario::TZ, [$o]);

    expect($shift->shiftId)->toBe(1)->and($shift->graceInMin)->toBe(20);
});
