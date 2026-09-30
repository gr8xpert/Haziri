<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

use App\Enums\AttendanceFlag;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveDayPart;
use App\Enums\MultiPunchMode;
use App\Enums\PunchDirection;
use Carbon\CarbonImmutable;

/**
 * Deterministic employee-day resolver (spec §7.3). Pure: same DayContext in → same DayResult out.
 *
 * Interpretations of the spec that are not spelled out there are recorded in docs/adr/0002-status-engine-rules.md.
 */
final class StatusEngine
{
    public function resolve(DayContext $ctx): DayResult
    {
        if ($ctx->joinedOn !== null && $ctx->date < $ctx->joinedOn) {
            return new DayResult(AttendanceStatus::NotJoined);
        }
        if ($ctx->leftOn !== null && $ctx->date > $ctx->leftOn) {
            return new DayResult(AttendanceStatus::Left);
        }

        $shift = $ctx->shift;
        [$start, $end] = $this->shiftWindow($ctx);
        $punches = $this->punchesInWindow($ctx, $start, $end);

        $flags = [];
        $sourceSummary = [];
        foreach ($punches as $p) {
            $sourceSummary[$p->source->value] = ($sourceSummary[$p->source->value] ?? 0) + 1;
            if ($p->isManual) {
                $flags[AttendanceFlag::ManualPunch->value] = AttendanceFlag::ManualPunch;
            }
            if ($p->geofenceFail) {
                $flags[AttendanceFlag::GeofenceFail->value] = AttendanceFlag::GeofenceFail;
            }
        }

        $breakMin = $shift?->breakMin ?? 0;
        $expectedMin = max(0, $this->minutes($start, $end) - $breakMin);

        $pairing = $this->pair($punches, $shift?->multiPunchMode ?? MultiPunchMode::FirstLast, $breakMin);
        $firstIn = $pairing['firstIn'];
        $lastOut = $pairing['lastOut'];
        $workedMin = $pairing['workedMin'];
        $missingOut = $pairing['missingOut'];
        $missingIn = $pairing['missingIn'];

        if ($missingOut) {
            $flags[AttendanceFlag::MissingOut->value] = AttendanceFlag::MissingOut;
            $autoClose = $shift?->autoCloseMissingOutAfterMin;
            if ($autoClose !== null && $firstIn !== null && $ctx->now->greaterThan($firstIn->addMinutes($autoClose))) {
                // Close the open segment so the day counts as a full scheduled day.
                $openIn = $pairing['openIn'] ?? $firstIn;
                $closeAt = $firstIn->addMinutes($expectedMin + $breakMin);
                $lastOut = $closeAt->greaterThan($openIn) ? $closeAt : $openIn;
                $workedMin = $pairing['closedMin'] + $this->minutes($openIn, $lastOut);
                $workedMin = max(0, $workedMin - $pairing['unpaidBreakLeft']);
                $flags[AttendanceFlag::AutoClosed->value] = AttendanceFlag::AutoClosed;
            }
        }
        if ($missingIn) {
            $flags[AttendanceFlag::MissingIn->value] = AttendanceFlag::MissingIn;
        }

        $leave = $ctx->leave;
        if ($leave?->isShortLeave) {
            // A few hours off: flagged only; the day is still judged on its punches.
            $flags[AttendanceFlag::ShortLeave->value] = AttendanceFlag::ShortLeave;
            $leave = null;
        }

        $weekday = (int) CarbonImmutable::parse($ctx->date, $ctx->timezone)->dayOfWeek;
        $isWeeklyOff = $shift !== null && in_array($weekday, $shift->weeklyOffDays, true);
        $isWorkingDay = ! ($leave?->sandwich) && ! $ctx->isHoliday && ! $isWeeklyOff
            && ! ($leave !== null && $leave->dayPart === LeaveDayPart::Full);

        // Late / early only make sense on a scheduled working day.
        $lateMin = 0;
        $earlyOutMin = 0;
        if ($shift !== null && $isWorkingDay) {
            if ($firstIn !== null && $leave?->dayPart !== LeaveDayPart::FirstHalf) {
                $afterStart = $this->minutes($start, $firstIn);
                if ($afterStart > $shift->lateThresholdMin()) {
                    $lateMin = max(0, $afterStart - $shift->graceInMin);
                    $flags[AttendanceFlag::Late->value] = AttendanceFlag::Late;
                }
            }
            $autoClosed = isset($flags[AttendanceFlag::AutoClosed->value]);
            if ($lastOut !== null && ! $missingOut && ! $autoClosed && $leave?->dayPart !== LeaveDayPart::SecondHalf) {
                $earlyOutMin = max(0, $this->minutes($lastOut, $end) - $shift->graceOutMin);
                if ($earlyOutMin > 0) {
                    $flags[AttendanceFlag::EarlyOut->value] = AttendanceFlag::EarlyOut;
                }
            }
        }

        $overtimeMin = $this->overtime($ctx, $workedMin, $expectedMin, $isWorkingDay);
        if ($overtimeMin > 0) {
            $flags[AttendanceFlag::Overtime->value] = AttendanceFlag::Overtime;
        }

        $hasPunches = $firstIn !== null || $lastOut !== null;
        $status = $this->status($ctx, $leave, $isWeeklyOff, $hasPunches, $lateMin, $workedMin, $missingOut || $missingIn, $end);

        if ($status === AttendanceStatus::HalfDayLeave && ! $hasPunches) {
            $flags[AttendanceFlag::MissingIn->value] = AttendanceFlag::MissingIn;
        }
        if ($leave?->sandwich && in_array($status, [AttendanceStatus::LeavePaid, AttendanceStatus::LeaveUnpaid], true)) {
            $flags[AttendanceFlag::SandwichApplied->value] = AttendanceFlag::SandwichApplied;
        }
        if ($ctx->manualOverride !== null) {
            $status = $ctx->manualOverride;
            $flags[AttendanceFlag::ManualOverride->value] = AttendanceFlag::ManualOverride;
        }

        return new DayResult(
            status: $status,
            flags: array_values($flags),
            firstIn: $firstIn,
            lastOut: $lastOut,
            workedMin: $workedMin,
            breakMin: $breakMin,
            lateMin: $lateMin,
            earlyOutMin: $earlyOutMin,
            overtimeMin: $overtimeMin,
            expectedMin: $expectedMin,
            shiftId: $shift?->shiftId,
            overrideId: $shift?->overrideId,
            holidayId: $ctx->isHoliday ? $ctx->holidayId : null,
            leaveRequestId: $ctx->leave?->leaveRequestId,
            sourceSummary: $sourceSummary,
        );
    }

    private function status(
        DayContext $ctx,
        ?LeaveInput $leave,
        bool $isWeeklyOff,
        bool $hasPunches,
        int $lateMin,
        int $workedMin,
        bool $incompletePair,
        CarbonImmutable $shiftEnd,
    ): ?AttendanceStatus {
        $shift = $ctx->shift;

        if ($leave?->sandwich) {
            return $leave->isPaid ? AttendanceStatus::LeavePaid : AttendanceStatus::LeaveUnpaid;
        }
        if ($ctx->isHoliday) {
            return AttendanceStatus::Holiday;
        }
        if ($isWeeklyOff) {
            return AttendanceStatus::WeeklyOff;
        }
        if ($leave !== null && $leave->dayPart === LeaveDayPart::Full) {
            return $leave->isPaid ? AttendanceStatus::LeavePaid : AttendanceStatus::LeaveUnpaid;
        }
        if ($ctx->marker !== null) {
            return $ctx->marker;
        }

        if (! $hasPunches) {
            if (! $this->dayIsOver($ctx, $shiftEnd)) {
                return null;
            }

            return $leave?->dayPart->isHalf() ? AttendanceStatus::HalfDayLeave : AttendanceStatus::Absent;
        }

        if ($leave?->dayPart->isHalf()) {
            return AttendanceStatus::HalfDayLeave;
        }
        if ($shift?->halfDayIfLateAfterMin !== null && $lateMin > $shift->halfDayIfLateAfterMin) {
            return AttendanceStatus::HalfDay;
        }
        if ($shift?->halfDayIfWorkedLessThanMin !== null && ! $incompletePair && $workedMin < $shift->halfDayIfWorkedLessThanMin) {
            return AttendanceStatus::HalfDay;
        }

        return AttendanceStatus::Present;
    }

    /** Absence is final only after the day (and the shift) has ended, plus `auto_absent_after_days - 1` days. */
    private function dayIsOver(DayContext $ctx, CarbonImmutable $shiftEnd): bool
    {
        $days = $ctx->settings->autoAbsentAfterDays;
        if ($days <= 0) {
            return $ctx->now->greaterThanOrEqualTo($shiftEnd);
        }
        $nextMidnight = CarbonImmutable::parse($ctx->date, $ctx->timezone)->addDay();
        $base = $shiftEnd->greaterThan($nextMidnight) ? $shiftEnd : $nextMidnight;

        return $ctx->now->greaterThanOrEqualTo($base->addDays($days - 1));
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} scheduled start/end in UTC */
    private function shiftWindow(DayContext $ctx): array
    {
        if ($ctx->shift === null) {
            $start = CarbonImmutable::parse($ctx->date, $ctx->timezone);

            return [$start->utc(), $start->addDay()->utc()];
        }
        $start = CarbonImmutable::parse("{$ctx->date} {$ctx->shift->startTime}", $ctx->timezone);
        $end = CarbonImmutable::parse("{$ctx->date} {$ctx->shift->endTime}", $ctx->timezone);
        if ($ctx->shift->crossesMidnight || $end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }

    /**
     * Valid punches inside [start - before, end + after], sorted, with double taps within the dedupe window dropped.
     *
     * @return list<PunchInput>
     */
    private function punchesInWindow(DayContext $ctx, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $from = $start->subMinutes($ctx->settings->punchSearchWindowBeforeMin);
        $to = $end->addMinutes($ctx->settings->punchSearchWindowAfterMin);

        $inWindow = array_values(array_filter(
            $ctx->punches,
            fn (PunchInput $p) => $p->at->greaterThanOrEqualTo($from) && $p->at->lessThanOrEqualTo($to),
        ));
        usort($inWindow, fn (PunchInput $a, PunchInput $b) => $a->at->getTimestamp() <=> $b->at->getTimestamp());

        $kept = [];
        foreach ($inWindow as $p) {
            $prev = end($kept);
            if ($prev !== false && $p->at->getTimestamp() - $prev->at->getTimestamp() < $ctx->settings->dedupeWindowSec) {
                continue;
            }
            $kept[] = $p;
        }

        return $kept;
    }

    /**
     * @param  list<PunchInput>  $punches
     * @return array{firstIn: ?CarbonImmutable, lastOut: ?CarbonImmutable, workedMin: int, missingOut: bool, missingIn: bool, openIn: ?CarbonImmutable, closedMin: int, unpaidBreakLeft: int}
     */
    private function pair(array $punches, MultiPunchMode $mode, int $breakMin): array
    {
        $result = [
            'firstIn' => null, 'lastOut' => null, 'workedMin' => 0,
            'missingOut' => false, 'missingIn' => false,
            'openIn' => null, 'closedMin' => 0, 'unpaidBreakLeft' => $breakMin,
        ];
        $count = count($punches);
        if ($count === 0) {
            return $result;
        }

        if ($count === 1) {
            if ($punches[0]->direction === PunchDirection::Out) {
                $result['lastOut'] = $punches[0]->at;
                $result['missingIn'] = true;
            } else {
                $result['firstIn'] = $punches[0]->at;
                $result['openIn'] = $punches[0]->at;
                $result['missingOut'] = true;
            }

            return $result;
        }

        $result['firstIn'] = $punches[0]->at;

        if ($mode === MultiPunchMode::FirstLast) {
            $result['lastOut'] = $punches[$count - 1]->at;
            $span = $this->minutes($result['firstIn'], $result['lastOut']);
            $result['workedMin'] = max(0, $span - $breakMin);

            return $result;
        }

        // Paired: (1st,2nd), (3rd,4th)… — time between pairs is already unpaid, so only the rest of the break is deducted.
        $sum = 0;
        $gaps = 0;
        $prevOut = null;
        for ($i = 0; $i + 1 < $count; $i += 2) {
            $in = $punches[$i]->at;
            $out = $punches[$i + 1]->at;
            $sum += $this->minutes($in, $out);
            if ($prevOut !== null) {
                $gaps += $this->minutes($prevOut, $in);
            }
            $prevOut = $out;
        }
        $result['lastOut'] = $prevOut;
        $result['unpaidBreakLeft'] = max(0, $breakMin - $gaps);
        $result['closedMin'] = $sum;
        $result['workedMin'] = max(0, $sum - $result['unpaidBreakLeft']);
        if ($count % 2 === 1) {
            $result['missingOut'] = true;
            $result['openIn'] = $punches[$count - 1]->at;
        }

        return $result;
    }

    private function overtime(DayContext $ctx, int $workedMin, int $expectedMin, bool $isWorkingDay): int
    {
        $shift = $ctx->shift;
        if (! $ctx->overtimeEligible || ! $ctx->settings->overtimeEnabled || $shift === null) {
            return 0;
        }
        if ($isWorkingDay) {
            $extra = $workedMin - $expectedMin;
            if ($extra <= ($shift->overtimeAfterMin ?? 0)) {
                return 0;
            }
        } else {
            // Any work on a holiday / weekly off is overtime.
            $extra = $workedMin;
        }
        if ($shift->maxOvertimeMin !== null) {
            $extra = min($extra, $shift->maxOvertimeMin);
        }
        $rounding = max(1, $ctx->settings->overtimeRoundingMin);

        return max(0, intdiv($extra, $rounding) * $rounding);
    }

    private function minutes(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return intdiv($to->getTimestamp() - $from->getTimestamp(), 60);
    }
}
