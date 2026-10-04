<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\CorrectionType;
use App\Enums\PunchDirection;
use App\Enums\RequestStatus;
use App\Models\Concerns\ScopedToUserBranches;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AttendanceCorrection extends Model
{
    use LogsActivity;
    use ScopedToUserBranches;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'type' => CorrectionType::class,
            'status' => RequestStatus::class,
            'payload' => 'array',
            'approver_chain' => 'array',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** What is being asked for, in plain words ("Out punch at 18:10", "Change to Present"). */
    public function detailsText(): string
    {
        $payload = $this->payload ?? [];

        return match ($this->type) {
            CorrectionType::AddPunch => __(':direction punch at :time', [
                'direction' => PunchDirection::tryFrom((string) ($payload['direction'] ?? ''))?->getLabel() ?? __('A'),
                'time' => $payload['time'] ?? '?',
            ]),
            CorrectionType::RemovePunch => $this->removedPunchText((int) ($payload['punch_id'] ?? 0)),
            CorrectionType::ChangeStatus => __('Change to :status', [
                'status' => AttendanceStatus::tryFrom((string) ($payload['status'] ?? ''))?->getLabel() ?? '?',
            ]),
            CorrectionType::MarkOd => __('Whole day on official duty'),
            CorrectionType::MarkWfh => __('Whole day working from home'),
        };
    }

    /** Who asked: the employee themself, or someone on their behalf. */
    public function requestedByText(): string
    {
        $requester = $this->requester;
        if ($requester === null) {
            return '—';
        }

        return $requester->employee_id !== null && $requester->employee_id === $this->employee_id
            ? __(':name (self)', ['name' => $requester->name])
            : $requester->name;
    }

    /** Where the request is in its approval chain ("Waiting for manager"), or who decided it. */
    public function stageText(): string
    {
        $chain = $this->approver_chain ?? [];
        if ($this->status === RequestStatus::Pending) {
            return match ($chain[$this->current_step]['step'] ?? 'hr') {
                'manager' => __('Waiting for manager'),
                default => __('Waiting for HR'),
            };
        }
        $decidedBy = collect($chain)->whereNotNull('decided_by')->last()['decided_by'] ?? null;
        $name = $decidedBy ? User::query()->whereKey($decidedBy)->value('name') : null;

        return $name ? __(':status by :name', ['status' => $this->status->getLabel(), 'name' => $name]) : $this->status->getLabel();
    }

    private function removedPunchText(int $punchId): string
    {
        $punch = Punch::withTrashed()->find($punchId);
        if ($punch === null) {
            return __('Remove a punch');
        }
        $time = $punch->punched_at->setTimezone(tenant('timezone') ?? config('hazri.default_timezone'))->format('H:i');

        return __('Remove the :time punch (:source)', ['time' => $time, 'source' => $punch->source->getLabel()]);
    }
}
