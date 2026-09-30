<?php

declare(strict_types=1);

namespace App\Actions\Leave;

use App\Enums\RequestStatus;
use App\Events\LeaveDecided;
use App\Models\AttendancePeriod;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Leave\LeaveBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Employee cancels own pending leave; HR (leave.approve_final) may cancel approved leave in an open month. */
final class CancelLeaveRequest
{
    public function __construct(private readonly LeaveBalanceService $balances) {}

    public function __invoke(LeaveRequest $request, User $by, ?string $reason = null): LeaveRequest
    {
        $isOwn = $by->employee_id !== null && $by->employee_id === $request->employee_id;
        $isHr = $by->can('leave.approve_final');

        $allowed = match ($request->status) {
            RequestStatus::Pending => $isOwn || $isHr,
            RequestStatus::Approved => $isHr && ! AttendancePeriod::isLocked($request->from_date->format('Y-m-d')),
            default => false,
        };
        if (! $allowed) {
            throw ValidationException::withMessages(['cancel' => __('This request cannot be cancelled.')]);
        }

        return DB::transaction(function () use ($request, $by, $reason) {
            $balance = $this->balances->balance($request->employee, $request->leaveType, (int) $request->from_date->format('Y'));
            if ($request->status === RequestStatus::Pending) {
                $balance->decrement('pending', $request->days);
            } else {
                $balance->decrement('used', $request->days + count($request->sandwich_dates ?? []));
            }
            $request->update([
                'status' => RequestStatus::Cancelled,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'decided_note' => $reason,
                'sandwich_dates' => null,
            ]);
            LeaveDecided::dispatch($request, true);

            return $request;
        });
    }
}
