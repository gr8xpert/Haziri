<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\ShiftOverrideScope;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftOverride;
use App\Services\Attendance\Engine\ResolvedShift;
use App\Services\Attendance\Engine\ShiftOverrideApplier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spec §7.3 resolveShift: latest effective assignment → tenant default shift, then overrides by priority.
 */
final class ShiftResolver
{
    public function __construct(private readonly ShiftOverrideApplier $applier) {}

    public function resolve(Employee $employee, string $date, string $timezone): ?ResolvedShift
    {
        $shift = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first()?->shift
            ?? Shift::query()->where('is_default', true)->first();

        if ($shift === null) {
            return null;
        }

        $branchOffs = $employee->branch?->weekly_off_days;
        $resolved = $shift->toResolved($branchOffs !== null ? array_map('intval', $branchOffs) : [0]);

        $overrides = ShiftOverride::query()
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date)
            ->where(function (Builder $q) use ($employee, $shift) {
                $q->where('scope', ShiftOverrideScope::Tenant)
                    ->orWhere(fn (Builder $b) => $b->where('scope', ShiftOverrideScope::Branch)->where('scope_id', $employee->branch_id))
                    ->orWhere(fn (Builder $b) => $b->where('scope', ShiftOverrideScope::Department)->where('scope_id', $employee->department_id))
                    ->orWhere(fn (Builder $b) => $b->where('scope', ShiftOverrideScope::Shift)->where('scope_id', $shift->id));
            })
            ->get()
            ->map(fn (ShiftOverride $o) => $o->toInput())
            ->all();

        return $this->applier->apply($resolved, $date, $timezone, $overrides);
    }
}
