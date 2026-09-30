<?php

declare(strict_types=1);

namespace App\Actions\Corrections;

use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\PunchData;
use App\Enums\AttendanceStatus;
use App\Enums\CorrectionType;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\RequestStatus;
use App\Events\CorrectionDecided;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\AttendanceMarker;
use App\Models\AttendancePeriod;
use App\Models\Punch;
use App\Models\User;
use App\Services\Approvals\ApprovalChain;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Decide the current step; on final approval the correction is applied automatically (spec §9 phase 2). */
final class DecideCorrection
{
    public function __construct(
        private readonly ApprovalChain $chains,
        private readonly IngestPunchAction $ingest,
    ) {}

    public function __invoke(AttendanceCorrection $correction, User $by, bool $approve, ?string $note = null): AttendanceCorrection
    {
        if ($correction->status !== RequestStatus::Pending) {
            throw ValidationException::withMessages(['decision' => __('This request is no longer pending.')]);
        }
        $chain = $correction->approver_chain ?? [];
        if (! $this->chains->canDecide($by, $chain, $correction->current_step, $correction->employee, 'corrections.approve_final', 'corrections.approve_first')) {
            throw ValidationException::withMessages(['decision' => __('You cannot decide this step.')]);
        }
        if ($approve && AttendancePeriod::isLocked($correction->date->format('Y-m-d'))) {
            throw ValidationException::withMessages(['decision' => __('That month is locked.')]);
        }

        return DB::transaction(function () use ($correction, $by, $approve, $note, $chain) {
            $result = $this->chains->decide($chain, $correction->current_step, $by, $approve, $note, $by->can('corrections.approve_final'));
            $correction->approver_chain = $result['chain'];
            $correction->current_step = $result['current'];
            if ($result['final']) {
                $correction->status = $approve ? RequestStatus::Approved : RequestStatus::Rejected;
                $correction->decided_by = $by->id;
                $correction->decided_at = now();
                if ($approve) {
                    $this->apply($correction, $by);
                }
            }
            $correction->save();
            CorrectionDecided::dispatch($correction, $result['final']);

            return $correction;
        });
    }

    private function apply(AttendanceCorrection $c, User $by): void
    {
        $date = $c->date->format('Y-m-d');
        $reason = "Correction #{$c->id}: {$c->reason}";

        match ($c->type) {
            CorrectionType::AddPunch => ($this->ingest)(new PunchData(
                punchedAt: CarbonImmutable::parse("{$date} {$c->payload['time']}", tenant('timezone') ?? config('hazri.default_timezone'))->utc(),
                source: PunchSource::Manual,
                employeeId: $c->employee_id,
                direction: PunchDirection::from($c->payload['direction']),
                isManual: true,
                reason: $reason,
                enteredByUserId: $by->id,
                meta: ['correction_id' => $c->id],
            )),
            CorrectionType::RemovePunch => tap(Punch::query()->findOrFail($c->payload['punch_id']), function (Punch $p) use ($c, $by) {
                $p->update(['meta' => array_merge($p->meta ?? [], ['removed_by_correction' => $c->id, 'removed_by' => $by->id])]);
                $p->delete();
            }),
            CorrectionType::ChangeStatus => AttendanceDay::query()->firstOrCreate(['employee_id' => $c->employee_id, 'date' => $date])
                ->update(['manual_override' => [
                    'status' => AttendanceStatus::from($c->payload['status'])->value,
                    'reason' => $reason, 'user_id' => $by->id, 'at' => now()->toIso8601String(),
                ]]),
            CorrectionType::MarkOd, CorrectionType::MarkWfh => AttendanceMarker::query()->updateOrCreate(
                ['employee_id' => $c->employee_id, 'date' => $date],
                ['status' => $c->type === CorrectionType::MarkOd ? AttendanceStatus::OfficialDuty : AttendanceStatus::Wfh,
                    'reason' => $reason, 'created_by' => $by->id],
            ),
        };
    }
}
