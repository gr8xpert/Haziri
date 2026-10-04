<?php

declare(strict_types=1);

use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus as S;
use App\Services\Attendance\Engine\DayResult;
use App\Services\Attendance\Period\PeriodCalculator;
use App\Services\Attendance\Period\PeriodRules;

/** @return list<DayResult> */
function days(array $spec): array
{
    $out = [];
    foreach ($spec as [$status, $n, $flags]) {
        for ($i = 0; $i < $n; $i++) {
            $out[] = new DayResult($status, $flags);
        }
    }

    return $out;
}

it('rolls up N lates into deduction days', function () {
    $month = days([
        [S::Present, 15, []],
        [S::Present, 7, [AttendanceFlag::Late]],
        [S::WeeklyOff, 4, []],
        [S::Absent, 2, []],
        [S::HalfDay, 2, []],
    ]);

    $s = (new PeriodCalculator)->summarise($month, new PeriodRules(latesPerAbsent: 3));

    expect($s->calendarDays)->toBe(30)
        ->and($s->lateCount)->toBe(7)
        ->and($s->lateRollupDays)->toBe(2)
        ->and($s->deductionDays)->toBe(5.0) // 2 absent + 1 (2 half) + 2 rollup
        ->and($s->payableDays)->toBe(25.0);
});

it('skips the late rollup for exempt employees and when disabled', function () {
    $month = days([[S::Present, 6, [AttendanceFlag::Late]]]);
    $calc = new PeriodCalculator;

    expect($calc->summarise($month, new PeriodRules(latesPerAbsent: 3, lateDeductionExempt: true))->lateRollupDays)->toBe(0)
        ->and($calc->summarise($month, new PeriodRules(latesPerAbsent: 0))->lateRollupDays)->toBe(0)
        ->and($calc->summarise($month, new PeriodRules(latesPerAbsent: 3))->lateRollupDays)->toBe(2);
});

it('counts unpaid leave and unworked half-day leave as deductions', function () {
    $month = days([
        [S::Present, 20, []],
        [S::LeaveUnpaid, 2, []],
        [S::HalfDayLeave, 1, [AttendanceFlag::MissingIn]],
        [S::HalfDayLeave, 1, []],
        [S::LeavePaid, 1, []],
    ]);

    $s = (new PeriodCalculator)->summarise($month, new PeriodRules);

    expect($s->deductionDays)->toBe(2.5)->and($s->payableDays)->toBe(22.5);
});

it('excludes not-joined, left and pending days from calendar days', function () {
    $month = [
        ...days([[S::NotJoined, 10, []], [S::Present, 15, []], [S::Left, 3, []]]),
        new DayResult(null),
    ];

    $s = (new PeriodCalculator)->summarise($month, new PeriodRules);

    expect($s->calendarDays)->toBe(15)->and($s->pendingDays)->toBe(1)->and($s->payableDays)->toBe(15.0);
});

it('pays daily-wage staff only for days worked (plus holidays if configured)', function () {
    $month = days([
        [S::Present, 20, []],
        [S::HalfDay, 2, []],
        [S::Absent, 3, []],
        [S::Holiday, 1, []],
        [S::WeeklyOff, 4, []],
    ]);
    $calc = new PeriodCalculator;

    expect($calc->summarise($month, new PeriodRules(isDailyWage: true))->payableDays)->toBe(21.0)
        ->and($calc->summarise($month, new PeriodRules(isDailyWage: true, dailyWagePaidHolidays: true))->payableDays)->toBe(22.0);
});

it('takes unpaid holidays off the pay but still counts them as holidays', function () {
    $month = days([
        [S::Present, 24, []],
        [S::WeeklyOff, 4, []],
        [S::Holiday, 1, []],
        [S::Holiday, 1, [AttendanceFlag::UnpaidHoliday]],
    ]);

    $s = (new PeriodCalculator)->summarise($month, new PeriodRules);

    expect($s->holidays)->toBe(2)->and($s->unpaidHolidays)->toBe(1)
        ->and($s->deductionDays)->toBe(1.0)->and($s->payableDays)->toBe(29.0);
});
