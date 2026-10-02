<?php

declare(strict_types=1);

use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus as S;
use App\Enums\ExemptionType;
use App\Services\Attendance\Engine\DayResult;
use App\Services\Attendance\Engine\StatusEngine;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRules;
use Tests\Support\Scenario;

beforeEach(fn () => $this->engine = new StatusEngine);

it('does not mark late arrivals late on a late-exempted day', function () {
    $r = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 11:30', '2026-09-01 18:00'], ['exemption' => ExemptionType::Late]));

    // 11:30 would normally be a half day (late > 120 min)
    expect($r->status)->toBe(S::Present)
        ->and($r->hasFlag(AttendanceFlag::Late))->toBeFalse()
        ->and($r->lateMin)->toBe(0)
        ->and($r->hasFlag(AttendanceFlag::Exempted))->toBeTrue();
});

it('still marks a no-show absent on a late-only exemption', function () {
    expect($this->engine->resolve(Scenario::day('2026-09-01', [], ['exemption' => ExemptionType::Late]))->status)->toBe(S::Absent);
});

it('excuses a no-show and a short day on a whole-day exemption', function () {
    $noShow = $this->engine->resolve(Scenario::day('2026-09-01', [], ['exemption' => ExemptionType::Absent]));
    $short = $this->engine->resolve(Scenario::day('2026-09-01', ['2026-09-01 09:00', '2026-09-01 11:00'], ['exemption' => ExemptionType::Absent]));

    expect($noShow->status)->toBe(S::Exempted)
        ->and($short->status)->toBe(S::Present);
});

it('lets holidays and weekly offs win over an exemption', function () {
    $sunday = $this->engine->resolve(Scenario::day('2026-09-06', [], ['exemption' => ExemptionType::Absent]));

    expect($sunday->status)->toBe(S::WeeklyOff);
});

/** @return list<DayResult> */
function adjustmentMonth(): array
{
    $days = [];
    foreach ([[S::Present, 16, []], [S::Present, 6, [AttendanceFlag::Late]], [S::HalfDay, 2, []], [S::Absent, 1, []], [S::WeeklyOff, 4, []], [S::Exempted, 1, []]] as [$status, $n, $flags]) {
        for ($i = 0; $i < $n; $i++) {
            $days[] = new DayResult($status, $flags);
        }
    }

    return $days;
}

it('charges late roll-ups and half days to leave up to the available balance', function () {
    $calc = new PeriodCalculator;
    // 6 lates → 2 roll-up days, 2 half days → 1 day: 3 days chargeable; 1 absent stays a salary deduction.
    $salary = $calc->summarise(adjustmentMonth(), new PeriodRules(latesPerAbsent: 3));
    $partly = $calc->summarise(adjustmentMonth(), new PeriodRules(latesPerAbsent: 3, leaveAdjustAvailable: 1.7));
    $fully = $calc->summarise(adjustmentMonth(), new PeriodRules(latesPerAbsent: 3, leaveAdjustAvailable: INF));

    expect($salary->leaveAdjustedDays)->toBe(0.0)->and($salary->deductionDays)->toBe(4.0)
        ->and($partly->leaveAdjustedDays)->toBe(1.5)->and($partly->deductionDays)->toBe(2.5)
        ->and($fully->leaveAdjustedDays)->toBe(3.0)->and($fully->deductionDays)->toBe(1.0)
        ->and($fully->payableDays)->toBe(29.0);
});

it('never charges daily-wage staff to leave and pays exempted days', function () {
    $s = (new PeriodCalculator)->summarise(adjustmentMonth(), new PeriodRules(latesPerAbsent: 3, isDailyWage: true, leaveAdjustAvailable: INF));

    expect($s->leaveAdjustedDays)->toBe(0.0)
        ->and($s->exemptedDays)->toBe(1)
        ->and($s->payableDays)->toBe(24.0); // 22 present + 1 exempted + 2 half days × 0.5
});
