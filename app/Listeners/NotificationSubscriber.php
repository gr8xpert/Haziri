<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Events\CorrectionDecided;
use App\Events\DeviceWentOffline;
use App\Events\LeaveDecided;
use App\Events\LeaveRequested;
use App\Events\PeriodLocked;
use App\Jobs\GenerateMonthlyNarrative;
use App\Models\AttendanceCorrection;
use App\Models\LeaveRequest;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\Recipients;
use App\Services\Webhooks\Webhooks;
use Illuminate\Events\Dispatcher;

/** Domain events → notifications (spec §11). */
final class NotificationSubscriber
{
    public function __construct(private readonly Notifier $notifier) {}

    public function leaveRequested(LeaveRequested $e): void
    {
        $this->askApprovers($e->request);
    }

    public function leaveDecided(LeaveDecided $e): void
    {
        $r = $e->request;
        if (! $e->final) {
            $this->askApprovers($r); // next step's approvers

            return;
        }
        Webhooks::dispatch('leave.decided', [
            'id' => $r->id, 'employee' => $r->employee?->code, 'type' => $r->leaveType?->code, 'status' => $r->status->value,
            'from' => $r->from_date->format('Y-m-d'), 'to' => $r->to_date->format('Y-m-d'), 'days' => $r->days,
        ]);
        $this->notifier->send('leave_decided', [Recipient::fromEmployee($r->employee)], $this->leaveData($r) + [
            'status' => __($r->status->value),
            'note' => (string) $r->decided_note,
        ]);
    }

    public function correctionDecided(CorrectionDecided $e): void
    {
        $c = $e->correction;
        if (! $e->final) {
            $this->notifier->send('correction_requested',
                Recipients::approversFor($c->approver_chain ?? [], $c->current_step, 'corrections.approve_final'),
                $this->correctionData($c), url('/admin/corrections'));

            return;
        }
        $this->notifier->send('correction_decided', [Recipient::fromEmployee($c->employee)], $this->correctionData($c) + [
            'status' => __($c->status->value),
        ]);
    }

    public function deviceOffline(DeviceWentOffline $e): void
    {
        $this->notifier->send('device_offline', Recipients::withRoles(Role::Owner, Role::Admin), [
            'device' => $e->device->name,
            'serial' => $e->device->serial_number,
            'last_seen' => $e->device->last_seen_at?->setTimezone(tenant('timezone'))->format('d M H:i') ?? '—',
        ], url('/admin/devices'));
    }

    public function periodLocked(PeriodLocked $e): void
    {
        GenerateMonthlyNarrative::dispatch($e->period->id); // spec §12.2 (no-op when AI is off)
        Webhooks::dispatch('period.locked', [
            'year' => $e->period->year, 'month' => $e->period->month, 'totals' => $e->period->summary['totals'] ?? [],
        ]);
        $p = $e->period;
        $this->notifier->send('period_locked', Recipients::withRoles(Role::Owner), [
            'month' => $p->label(),
            'by' => $e->by->name,
            'deduction_days' => $p->summary['totals']['deduction_days'] ?? 0,
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            LeaveRequested::class => 'leaveRequested',
            LeaveDecided::class => 'leaveDecided',
            CorrectionDecided::class => 'correctionDecided',
            DeviceWentOffline::class => 'deviceOffline',
            PeriodLocked::class => 'periodLocked',
        ];
    }

    private function askApprovers(LeaveRequest $r): void
    {
        if ($r->status !== RequestStatus::Pending) {
            return;
        }
        $this->notifier->send('leave_requested',
            Recipients::approversFor($r->approver_chain ?? [], $r->current_step, 'leave.approve_final'),
            $this->leaveData($r), url('/admin/leave-requests'));
    }

    /** @return array<string, mixed> */
    private function leaveData(LeaveRequest $r): array
    {
        return [
            'employee' => $r->employee?->fullName(),
            'name' => $r->employee?->first_name,
            'type' => $r->leaveType?->name,
            'from' => $r->from_date->format('d M Y'),
            'to' => $r->to_date->format('d M Y'),
            'days' => $r->days,
            'reason' => (string) $r->reason,
        ];
    }

    /** @return array<string, mixed> */
    private function correctionData(AttendanceCorrection $c): array
    {
        return [
            'employee' => $c->employee?->fullName(),
            'type' => str_replace('_', ' ', $c->type->value),
            'date' => $c->date->format('d M Y'),
            'reason' => $c->reason,
        ];
    }
}
