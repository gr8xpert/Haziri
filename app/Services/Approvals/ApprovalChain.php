<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Models\Employee;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use Illuminate\Validation\ValidationException;

/**
 * Approval chains for leave and corrections (spec §8): setting `leave_approval_chain` / `correction_approval_chain`
 * = ["manager","hr"] (default) or ["hr"]. A "manager" step is skipped when the employee has no manager with a login.
 *
 * Stored on the request as approver_chain = [{step, user_id|null, decided_by|null, decision|null, at|null, note|null}]
 * with current_step pointing at the next undecided step.
 */
final class ApprovalChain
{
    public function __construct(private readonly TenantSettings $settings) {}

    /** @return list<array<string, mixed>> */
    public function build(Employee $employee, string $settingKey): array
    {
        $steps = (array) $this->settings->get($settingKey, ['manager', 'hr']);
        $chain = [];
        foreach ($steps as $step) {
            if ($step === 'manager') {
                $managerUserId = $employee->manager_employee_id
                    ? User::query()->where('employee_id', $employee->manager_employee_id)->where('is_active', true)->value('id')
                    : null;
                if ($managerUserId === null) {
                    continue; // spec §8: manager missing → skip to next step
                }
                $chain[] = self::step('manager', $managerUserId);

                continue;
            }
            $chain[] = self::step((string) $step, null);
        }

        return $chain ?: [self::step('hr', null)];
    }

    /** Can $user decide the current step? HR-level (…approve_final) can decide any step. */
    public function canDecide(User $user, array $chain, int $current, Employee $employee, string $finalPermission, string $firstPermission): bool
    {
        $step = $chain[$current] ?? null;
        if ($step === null) {
            return false;
        }
        if ($user->can($finalPermission)) {
            return true;
        }
        if ($step['step'] === 'manager') {
            return $user->can($firstPermission)
                && ($step['user_id'] === $user->id || ($user->employee_id !== null && $user->employee_id === $employee->manager_employee_id));
        }

        return false;
    }

    /**
     * Record a decision on the current step. An approval by the final authority (HR-level) completes the chain;
     * any rejection ends it.
     *
     * @return array{chain: list<array<string, mixed>>, current: int, final: bool}
     */
    public function decide(array $chain, int $current, User $by, bool $approve, ?string $note, bool $finalAuthority = false): array
    {
        if (! isset($chain[$current])) {
            throw ValidationException::withMessages(['decision' => __('This request has already been decided.')]);
        }
        $chain[$current]['decided_by'] = $by->id;
        $chain[$current]['decision'] = $approve ? 'approved' : 'rejected';
        $chain[$current]['at'] = now()->toIso8601String();
        $chain[$current]['note'] = $note;

        $final = ! $approve || $finalAuthority || $current === count($chain) - 1;

        return ['chain' => $chain, 'current' => $final ? $current : $current + 1, 'final' => $final];
    }

    /** @return array<string, mixed> */
    private static function step(string $name, ?int $userId): array
    {
        return ['step' => $name, 'user_id' => $userId, 'decided_by' => null, 'decision' => null, 'at' => null, 'note' => null];
    }
}
