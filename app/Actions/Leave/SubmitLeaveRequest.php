<?php

declare(strict_types=1);

namespace App\Actions\Leave;

use App\Enums\LeaveDayPart;
use App\Enums\RequestStatus;
use App\Events\LeaveRequested;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
use App\Services\Attendance\WorkingDayCalendar;
use App\Services\Leave\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Employee (self) or HR (on behalf) applies for leave (spec §9 phase 2). */
final class SubmitLeaveRequest
{
    public function __construct(
        private readonly WorkingDayCalendar $calendar,
        private readonly LeaveBalanceService $balances,
        private readonly ApprovalChain $chains,
    ) {}

    public function __invoke(
        Employee $employee,
        LeaveType $type,
        string $from,
        string $to,
        User $by,
        LeaveDayPart $dayPart = LeaveDayPart::Full,
        ?string $reason = null,
        ?string $attachmentPath = null,
        ?string $shortFrom = null,
        ?string $shortTo = null,
    ): LeaveRequest {
        $days = $this->validate($employee, $type, $from, $to, $dayPart, $attachmentPath, $shortFrom, $shortTo);

        return DB::transaction(function () use ($employee, $type, $from, $to, $by, $dayPart, $reason, $attachmentPath, $shortFrom, $shortTo, $days) {
            $request = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'from_date' => $from,
                'to_date' => $to,
                'day_part' => $dayPart,
                'short_leave_from' => $shortFrom,
                'short_leave_to' => $shortTo,
                'days' => $days,
                'reason' => $reason,
                'attachment_path' => $attachmentPath,
                'status' => RequestStatus::Pending,
                'approver_chain' => $this->chains->build($employee, 'leave_approval_chain'),
                'current_step' => 0,
                'created_by' => $by->id,
            ]);
            $balance = $this->balances->balance($employee, $type, (int) substr($from, 0, 4));
            $balance->increment('pending', $days);

            LeaveRequested::dispatch($request);

            return $request;
        });
    }

    private function validate(Employee $employee, LeaveType $type, string $from, string $to, LeaveDayPart $dayPart,
        ?string $attachment, ?string $shortFrom, ?string $shortTo): float
    {
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if (! $type->is_active) {
            $fail('leave_type_id', __('This leave type is not active.'));
        }
        if ($type->applies_to && ! in_array($employee->employment_type?->value, $type->applies_to, true)) {
            $fail('leave_type_id', __('This leave type does not apply to :t employees.', ['t' => $employee->employment_type?->value]));
        }
        if ($type->gender_restriction && $employee->gender !== $type->gender_restriction) {
            $fail('leave_type_id', __('This leave type is not available for this employee.'));
        }
        if ($to < $from) {
            $fail('to_date', __('The end date must be on or after the start date.'));
        }
        if (substr($from, 0, 4) !== substr($to, 0, 4)) {
            $fail('to_date', __('Split leave that crosses a year end into two requests.'));
        }
        if (AttendancePeriod::isLocked($from) || AttendancePeriod::isLocked($to)) {
            $fail('from_date', __('That month is locked.'));
        }

        $isShort = $shortFrom !== null && $shortTo !== null;
        if ($isShort) {
            if (! $type->allow_short_leave_hours || $from !== $to) {
                $fail('short_leave_from', __('Short leave is not allowed for this type, or spans more than one day.'));
            }
            $hours = CarbonImmutable::parse($shortFrom)->diffInMinutes(CarbonImmutable::parse($shortTo)) / 60;
            if ($hours <= 0 || $hours > (float) $type->allow_short_leave_hours) {
                $fail('short_leave_to', __('Short leave can be at most :h hours.', ['h' => $type->allow_short_leave_hours]));
            }
        }
        if ($dayPart->isHalf() && (! $type->allow_half_day || $from !== $to)) {
            $fail('day_part', __('Half-day leave is not allowed for this type, or spans more than one day.'));
        }

        $days = match (true) {
            $isShort => 0.0,
            $dayPart->isHalf() => 0.5,
            (bool) $type->counts_weekends => (float) (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1),
            default => (float) count($this->calendar->workingDates($employee, $from, $to)),
        };
        if (! $isShort && $days <= 0) {
            $fail('from_date', __('There are no working days in the selected dates.'));
        }
        if ($type->max_consecutive_days && $days > $type->max_consecutive_days) {
            $fail('to_date', __('At most :n consecutive days are allowed.', ['n' => $type->max_consecutive_days]));
        }
        if ($type->requires_attachment_after_days !== null && $days > $type->requires_attachment_after_days && ! $attachment) {
            $fail('attachment_path', __('An attachment (e.g. medical certificate) is required for more than :n days.', ['n' => $type->requires_attachment_after_days]));
        }

        $overlap = LeaveRequest::query()->where('employee_id', $employee->id)
            ->whereIn('status', [RequestStatus::Pending, RequestStatus::Approved])
            ->where('from_date', '<=', $to)->where('to_date', '>=', $from)->exists();
        if ($overlap) {
            $fail('from_date', __('There is already a leave request for these dates.'));
        }

        if ($type->is_paid && $days > 0) {
            $remaining = $this->balances->balance($employee, $type, (int) substr($from, 0, 4))->remaining();
            if ($days > $remaining) {
                $fail('leave_type_id', __('Not enough balance: :r day(s) left, :d requested.', ['r' => $remaining, 'd' => $days]));
            }
        }

        return $days;
    }
}
