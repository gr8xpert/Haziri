<?php

declare(strict_types=1);

namespace App\Actions\Rosters;

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Enums\ShiftSwapStatus;
use App\Models\Employee;
use App\Models\ShiftSwapRequest;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\Recipients;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Shift swaps asked for in the app: the employee asks a colleague (status Partner), the colleague accepts (→ Pending,
 * HR / manager approves with ApproveShiftSwap) or declines. Each step notifies the next person.
 */
final class RequestShiftSwap
{
    /** Open requests one employee may have at a time, so a colleague can't be flooded with notifications. */
    public const MAX_OPEN = 3;

    public function __construct(
        private readonly ApproveShiftSwap $swaps,
        private readonly Notifier $notifier,
        private readonly TenantSettings $settings,
    ) {}

    public function ask(Employee $requester, Employee $partner, string $date, ?string $reason): ShiftSwapRequest
    {
        if (! $this->settings->featureEnabled('rosters')) {
            throw ValidationException::withMessages(['swap' => __('Shift swaps are not enabled for your company.')]);
        }
        if ($partner->id === $requester->id || $partner->status === EmployeeStatus::Left || ! self::areColleagues($requester, $partner)) {
            throw ValidationException::withMessages(['partner' => __('Choose a colleague to swap with.')]);
        }
        $waiting = ShiftSwapRequest::query()->where('requester_employee_id', $requester->id)
            ->whereIn('status', [ShiftSwapStatus::Partner, ShiftSwapStatus::Pending])->count();
        if ($waiting >= self::MAX_OPEN) {
            throw ValidationException::withMessages(['swap' => __('You already have :n swap requests waiting. Wait for an answer first.', ['n' => $waiting])]);
        }
        if ($date < CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d')) {
            throw ValidationException::withMessages(['date' => __('Choose today or a later date.')]);
        }
        $this->swaps->shiftIds($requester, $partner, $date); // both have a shift, and they differ
        $open = ShiftSwapRequest::query()->whereDate('date', $date)->whereIn('status', [ShiftSwapStatus::Partner, ShiftSwapStatus::Pending])
            ->where(fn ($q) => $q->whereIn('requester_employee_id', [$requester->id, $partner->id])->orWhereIn('partner_employee_id', [$requester->id, $partner->id]))
            ->exists();
        if ($open) {
            throw ValidationException::withMessages(['date' => __('There is already a swap request for one of you on that date.')]);
        }

        $swap = ShiftSwapRequest::create(['requester_employee_id' => $requester->id, 'partner_employee_id' => $partner->id,
            'date' => $date, 'reason' => $reason, 'status' => ShiftSwapStatus::Partner]);
        $this->notifier->send('shift_swap_asked', [Recipient::fromEmployee($partner)], [
            'employee' => $requester->fullName(), 'date' => $swap->date->format('d M Y'), 'reason' => (string) $reason,
        ], url('/me'), "swap_asked:{$swap->id}");

        return $swap;
    }

    /**
     * Who an employee may ask from the app: same branch and same department (when they have one). This also limits
     * whose names and shifts the app shows them.
     */
    public static function colleagues(Employee $employee): Builder
    {
        $same = fn ($q, string $column, ?int $value) => $value === null ? $q->whereNull($column) : $q->where($column, $value);

        return $same($same(Employee::query()->where('status', '!=', EmployeeStatus::Left)->whereKeyNot($employee->id),
            'branch_id', $employee->branch_id), 'department_id', $employee->department_id);
    }

    public static function areColleagues(Employee $a, Employee $b): bool
    {
        return self::colleagues($a)->whereKey($b->id)->exists();
    }

    /** The colleague's answer. Accepting sends it to HR / the manager for approval. */
    public function answer(ShiftSwapRequest $swap, Employee $partner, bool $accept, User $by): ShiftSwapRequest
    {
        if ($swap->partner_employee_id !== $partner->id || $swap->status !== ShiftSwapStatus::Partner) {
            throw ValidationException::withMessages(['swap' => __('This request is not waiting for your answer.')]);
        }
        $swap->update(['status' => $accept ? ShiftSwapStatus::Pending : ShiftSwapStatus::Declined,
            'decided_by' => $accept ? null : $by->id, 'decided_at' => $accept ? null : now()]);

        $data = ['employee' => $swap->requester->fullName(), 'partner' => $partner->fullName(), 'date' => $swap->date->format('d M Y')];
        if ($accept) {
            $this->notifier->send('shift_swap_pending', Recipients::withRoles(Role::Owner, Role::Admin, Role::Hr)->concat(Recipients::managerOf($swap->requester)),
                $data, url('/admin/shift-swaps'), "swap_pending:{$swap->id}");
        }
        $this->notifier->send('shift_swap_answered', [Recipient::fromEmployee($swap->requester)],
            $data + ['answer' => $accept ? __('accepted; it now waits for approval') : __('declined')], url('/me'), "swap_answered:{$swap->id}");

        return $swap;
    }
}
