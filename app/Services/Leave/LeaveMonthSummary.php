<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Enums\AttendanceStatus;
use App\Enums\RequestStatus;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One leave type's movement over one pay month: balance brought forward, allotted this month, availed, the late /
 * half-day adjustments made when the month is locked, HR's manual adjustments (credit, net), this type's leave without
 * pay and the closing balance. Leave counts in the month it starts; adjustments by their effective date. Used by the
 * monthly leave summary and the monthly attendance report.
 */
final class LeaveMonthSummary
{
    public function __construct(
        private readonly LeaveYear $leaveYear,
        private readonly LeaveBalanceService $balances,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array{previous: float, allotted: float, total: float, availed: float, adjusted: float, credit: float, lwp: int, closing: float}>
     */
    public function forEmployees(Collection $employees, LeaveType $type, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $from = $start->format('Y-m-d');
        $to = $end->format('Y-m-d');
        $year = $this->leaveYear->yearFor($to);
        [$yearStart] = $this->leaveYear->range($year);
        $ids = $employees->pluck('id');

        $openings = LeaveBalance::query()->where(['leave_type_id' => $type->id, 'year' => $year])->whereIn('employee_id', $ids)
            ->pluck('opening', 'employee_id');
        $requests = LeaveRequest::query()->where('leave_type_id', $type->id)->where('status', RequestStatus::Approved)
            ->whereIn('employee_id', $ids)->whereBetween('from_date', [$yearStart->format('Y-m-d'), $to])->get()->groupBy('employee_id');
        $adjustments = LeaveAdjustment::query()->where(['leave_type_id' => $type->id, 'year' => $year])->whereIn('employee_id', $ids)
            ->get()->groupBy('employee_id');
        $lwp = AttendanceDay::query()->whereIn('employee_id', $ids)->whereBetween('date', [$from, $to])
            ->where('status', AttendanceStatus::LeaveUnpaid)
            ->whereIn('leave_request_id', LeaveRequest::query()->withoutGlobalScopes()->select('id')->where('leave_type_id', $type->id))
            ->selectRaw('employee_id, count(*) as n')->groupBy('employee_id')->pluck('n', 'employee_id');
        $periodHasYearStart = $yearStart->betweenIncluded($start, $end);
        $daysOf = fn (LeaveRequest $r) => (float) $r->days + count($r->sandwich_dates ?? []);

        $out = [];
        foreach ($employees as $e) {
            if ($type->accrual === 'monthly') {
                $before = $this->balances->accrued($e, $type, $year, $start->subDay());
                $allotted = $this->balances->accrued($e, $type, $year, $end) - $before;
            } else {
                $full = $this->balances->accrued($e, $type, $year);
                $before = $periodHasYearStart ? 0.0 : $full;
                $allotted = $periodHasYearStart ? $full : 0.0;
            }
            $own = $requests->get($e->id, collect());
            $usedBefore = $own->filter(fn (LeaveRequest $r) => $r->from_date->format('Y-m-d') < $from)->sum($daysOf);
            $availed = (float) $own->filter(fn (LeaveRequest $r) => $r->from_date->format('Y-m-d') >= $from)->sum($daysOf);

            $adjBefore = 0.0;
            $deducted = 0.0;
            $credit = 0.0;
            foreach ($adjustments->get($e->id, collect()) as $a) {
                $on = ($a->effective_on ?? CarbonImmutable::parse($a->created_at))->format('Y-m-d');
                if ($on < $from) {
                    $adjBefore += $a->days;
                } elseif ($on <= $to && $a->attendance_period_id !== null) {
                    $deducted -= $a->days;
                } elseif ($on <= $to) {
                    $credit += $a->days;
                }
            }

            $previous = (float) ($openings[$e->id] ?? 0) + $before - $usedBefore + $adjBefore;
            $out[$e->id] = [
                'previous' => round($previous, 1),
                'allotted' => round($allotted, 1),
                'total' => round($previous + $allotted, 1),
                'availed' => round($availed, 1),
                'adjusted' => round($deducted, 1),
                'credit' => round($credit, 1),
                'lwp' => (int) ($lwp[$e->id] ?? 0),
                'closing' => round($previous + $allotted - $availed - $deducted + $credit, 1),
            ];
        }

        return $out;
    }
}
