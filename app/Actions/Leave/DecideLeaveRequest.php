<?php

declare(strict_types=1);

namespace App\Actions\Leave;

use App\Enums\RequestStatus;
use App\Events\LeaveDecided;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
use App\Services\Attendance\Engine\SandwichRule;
use App\Services\Attendance\WorkingDayCalendar;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approve / reject the current step. Final approval moves pending → used and applies the sandwich rule
 * (spec §7.3: computed at approval, stored as leave_requests.sandwich_dates, counted as leave days).
 */
final class DecideLeaveRequest
{
    public function __construct(
        private readonly ApprovalChain $chains,
        private readonly LeaveBalanceService $balances,
        private readonly WorkingDayCalendar $calendar,
        private readonly SandwichRule $sandwich,
        private readonly LeaveYear $leaveYear,
    ) {}

    public function __invoke(LeaveRequest $request, User $by, bool $approve, ?string $note = null): LeaveRequest
    {
        if ($request->status !== RequestStatus::Pending) {
            throw ValidationException::withMessages(['decision' => __('This request is no longer pending.')]);
        }
        $chain = $request->approver_chain ?? [];
        if (! $this->chains->canDecide($by, $chain, $request->current_step, $request->employee, 'leave.approve_final', 'leave.approve_first')) {
            throw ValidationException::withMessages(['decision' => __('You cannot decide this step.')]);
        }
        if (! $approve && blank($note)) {
            throw ValidationException::withMessages(['note' => __('Please give a reason for rejecting.')]);
        }

        return DB::transaction(function () use ($request, $by, $approve, $note, $chain) {
            $result = $this->chains->decide($chain, $request->current_step, $by, $approve, $note, $by->can('leave.approve_final'));
            $request->approver_chain = $result['chain'];
            $request->current_step = $result['current'];

            if ($result['final']) {
                $balance = $this->balances->balance($request->employee, $request->leaveType, $this->leaveYear->yearFor($request->from_date->format('Y-m-d')));
                $balance->decrement('pending', $request->days);
                if ($approve) {
                    $sandwich = $request->leaveType->sandwich_rule ? $this->sandwichDates($request) : [];
                    $request->sandwich_dates = $sandwich ?: null;
                    $balance->increment('used', $request->days + count($sandwich));
                }
                $request->status = $approve ? RequestStatus::Approved : RequestStatus::Rejected;
                $request->decided_by = $by->id;
                $request->decided_at = now();
                $request->decided_note = $note;
            }
            $request->save();

            LeaveDecided::dispatch($request, $result['final']);

            return $request;
        });
    }

    /** @return list<string> */
    private function sandwichDates(LeaveRequest $request): array
    {
        $employee = $request->employee;
        $own = $this->leaveDates($request);
        $near = LeaveRequest::query()->with('leaveType')
            ->where('employee_id', $employee->id)->where('status', RequestStatus::Approved)->whereKeyNot($request->id)
            ->where('from_date', '<=', $request->to_date->copy()->addDays(10))
            ->where('to_date', '>=', $request->from_date->copy()->subDays(10))
            ->get()->filter(fn (LeaveRequest $l) => $l->leaveType?->sandwich_rule);

        $others = $near->flatMap(fn (LeaveRequest $l) => $this->leaveDates($l))->all();
        $alreadySandwiched = $near->flatMap(fn (LeaveRequest $l) => $l->sandwich_dates ?? [])->all();

        $dates = $this->sandwich->sandwichedDates(
            array_values(array_unique([...$own, ...$others])),
            fn (string $d) => $this->calendar->isNonWorkingDay($employee, $d),
        );

        return array_values(array_diff($dates, $alreadySandwiched));
    }

    /** @return list<string> working dates actually taken as leave (full days only) */
    private function leaveDates(LeaveRequest $request): array
    {
        if ($request->day_part->isHalf() || $request->short_leave_from !== null) {
            return [];
        }
        $dates = [];
        foreach (CarbonPeriod::create($request->from_date, $request->to_date) as $d) {
            if (! $this->calendar->isNonWorkingDay($request->employee, $d->format('Y-m-d'))) {
                $dates[] = $d->format('Y-m-d');
            }
        }

        return $dates;
    }
}
