<?php

declare(strict_types=1);

namespace App\Actions\SelfService;

use App\Actions\Punches\IngestPunchAction;
use App\Enums\PunchStatus;
use App\Models\Punch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** HR approves / rejects an out-of-geofence mobile punch (spec §6.6). */
final class DecidePendingPunch
{
    public function __invoke(Punch $punch, User $by, bool $approve, ?string $note = null): Punch
    {
        if ($punch->status !== PunchStatus::Pending) {
            throw ValidationException::withMessages(['punch' => __('This punch is not waiting for approval.')]);
        }
        if (! $by->can('attendance.manual_edit')) {
            throw ValidationException::withMessages(['punch' => __('You cannot approve punches.')]);
        }
        $punch->update([
            'status' => $approve ? PunchStatus::Valid : PunchStatus::Rejected,
            'meta' => array_merge($punch->meta ?? [], ['decided_by' => $by->id, 'decided_at' => now()->toIso8601String(), 'decision_note' => $note]),
        ]);
        if ($approve) {
            IngestPunchAction::queueRecompute($punch->employee_id, $punch->local_date->format('Y-m-d'));
        }

        return $punch;
    }
}
