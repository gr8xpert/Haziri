<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Enums\AttendanceStatus;
use App\Enums\CorrectionType;
use App\Enums\PunchDirection;
use App\Enums\RequestStatus;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Attendance correction / OD / WFH request (spec §5.5, §9 phase 2). Payload by type:
 *  add_punch {time: "HH:MM", direction: in|out} · remove_punch {punch_id} · change_status {status}
 *  mark_od / mark_wfh {} (the request date)
 */
final class SubmitCorrection
{
    public function __construct(private readonly ApprovalChain $chains) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(Employee $employee, string $date, CorrectionType $type, array $payload, string $reason, User $by,
        ?string $attachmentPath = null): AttendanceCorrection
    {
        $this->validate($employee, $date, $type, $payload, $reason, $by);

        return AttendanceCorrection::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'requested_by' => $by->id,
            'type' => $type,
            'payload' => $payload,
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            'status' => RequestStatus::Pending,
            'approver_chain' => $this->chains->build($employee, 'correction_approval_chain'),
            'current_step' => 0,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function validate(Employee $employee, string $date, CorrectionType $type, array $payload, string $reason, User $by): void
    {
        $errors = [];
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $isFuture = $date > CarbonImmutable::now($tz)->format('Y-m-d');

        if (trim($reason) === '') {
            $errors['reason'] = __('Please give a reason.');
        }
        if (AttendancePeriod::isLocked($date)) {
            $errors['date'] = __('That month is locked.');
        }
        if ($isFuture && ! in_array($type, [CorrectionType::MarkOd, CorrectionType::MarkWfh], true)) {
            $errors['date'] = __('Only OD / WFH can be requested for future dates.');
        }
        $isSelf = $by->employee_id === $employee->id;
        $isManagerOf = $by->employee_id !== null && $employee->manager_employee_id === $by->employee_id;
        if (! $isSelf && ! $by->can('corrections.approve_final') && ! ($isManagerOf && $by->can('od_wfh.request_team'))) {
            $errors['employee'] = __('You can only request corrections for yourself or your team.');
        }

        match ($type) {
            CorrectionType::AddPunch => (! preg_match('/^\d{1,2}:\d{2}$/', (string) ($payload['time'] ?? ''))
                || ! PunchDirection::tryFrom((string) ($payload['direction'] ?? '')))
                ? $errors['payload'] = __('Give the time (HH:MM) and whether it was in or out.') : null,
            CorrectionType::RemovePunch => Punch::query()->whereKey($payload['punch_id'] ?? 0)
                ->where('employee_id', $employee->id)->whereDate('local_date', $date)->doesntExist()
                ? $errors['payload'] = __('That punch does not belong to this employee and date.') : null,
            CorrectionType::ChangeStatus => AttendanceStatus::tryFrom((string) ($payload['status'] ?? '')) === null
                ? $errors['payload'] = __('Choose a valid status.') : null,
            default => null,
        };

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
