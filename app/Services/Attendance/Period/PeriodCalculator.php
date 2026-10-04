<?php

declare(strict_types=1);

namespace App\Services\Attendance\Period;

use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus;
use App\Services\Attendance\Engine\DayResult;

/**
 * Monthly rollup of resolved days (spec §7.5). Pure — takes the month's DayResults, returns totals.
 */
final class PeriodCalculator
{
    /**
     * @param  iterable<DayResult>  $days
     */
    public function summarise(iterable $days, PeriodRules $rules): PeriodSummary
    {
        $count = array_fill_keys(array_map(fn (AttendanceStatus $s) => $s->value, AttendanceStatus::cases()), 0);
        $pending = 0;
        $lates = 0;
        $earlyOuts = 0;
        $worked = 0;
        $overtime = 0;
        $halfLeaveUnworked = 0;
        $unpaidHolidays = 0;

        foreach ($days as $day) {
            if ($day->status === null) {
                $pending++;

                continue;
            }
            $count[$day->status->value]++;
            $lates += $day->hasFlag(AttendanceFlag::Late) ? 1 : 0;
            $earlyOuts += $day->hasFlag(AttendanceFlag::EarlyOut) ? 1 : 0;
            $worked += $day->workedMin;
            $overtime += $day->overtimeMin;
            $unpaidHolidays += $day->status === AttendanceStatus::Holiday && $day->hasFlag(AttendanceFlag::UnpaidHoliday) ? 1 : 0;
            if ($day->status === AttendanceStatus::HalfDayLeave && $day->hasFlag(AttendanceFlag::MissingIn)) {
                $halfLeaveUnworked++;
            }
        }

        $c = fn (AttendanceStatus $s): int => $count[$s->value];

        $lateRollup = ($rules->latesPerAbsent > 0 && ! $rules->lateDeductionExempt)
            ? intdiv($lates, $rules->latesPerAbsent)
            : 0;

        $lateAndHalfDays = $c(AttendanceStatus::HalfDay) * $rules->halfDayCountsAs + $lateRollup;
        $leaveAdjusted = $rules->isDailyWage ? 0.0 : min(floor(max(0.0, $rules->leaveAdjustAvailable) * 2) / 2, $lateAndHalfDays);

        // An unpaid holiday is not worked and not paid: it comes off like a day without pay.
        $deduction = $c(AttendanceStatus::Absent) * $rules->absentDeductionDays
            + $unpaidHolidays
            + $c(AttendanceStatus::LeaveUnpaid) * $rules->unpaidLeaveDeductionDays
            + $halfLeaveUnworked * 0.5
            + $lateAndHalfDays - $leaveAdjusted;

        $calendarDays = array_sum($count) - $c(AttendanceStatus::NotJoined) - $c(AttendanceStatus::Left);

        if ($rules->isDailyWage) {
            $payable = $c(AttendanceStatus::Present) + $c(AttendanceStatus::OfficialDuty) + $c(AttendanceStatus::Wfh) + $c(AttendanceStatus::Exempted)
                + $c(AttendanceStatus::HalfDay) * (1 - $rules->halfDayCountsAs)
                + ($c(AttendanceStatus::HalfDayLeave) - $halfLeaveUnworked) * 0.5
                + ($rules->dailyWagePaidHolidays ? $c(AttendanceStatus::Holiday) - $unpaidHolidays : 0);
        } else {
            $payable = $calendarDays - $deduction;
        }

        return new PeriodSummary(
            calendarDays: $calendarDays,
            presentDays: $c(AttendanceStatus::Present),
            halfDays: $c(AttendanceStatus::HalfDay),
            halfDayLeaves: $c(AttendanceStatus::HalfDayLeave),
            absentDays: $c(AttendanceStatus::Absent),
            paidLeaveDays: $c(AttendanceStatus::LeavePaid),
            unpaidLeaveDays: $c(AttendanceStatus::LeaveUnpaid),
            holidays: $c(AttendanceStatus::Holiday),
            unpaidHolidays: $unpaidHolidays,
            weeklyOffs: $c(AttendanceStatus::WeeklyOff),
            officialDutyDays: $c(AttendanceStatus::OfficialDuty),
            wfhDays: $c(AttendanceStatus::Wfh),
            exemptedDays: $c(AttendanceStatus::Exempted),
            pendingDays: $pending,
            lateCount: $lates,
            earlyOutCount: $earlyOuts,
            workedMinutes: $worked,
            overtimeMinutes: $overtime,
            lateRollupDays: $lateRollup,
            leaveAdjustedDays: round($leaveAdjusted, 2),
            deductionDays: round($deduction, 2),
            payableDays: round(max(0.0, $payable), 2),
        );
    }
}
