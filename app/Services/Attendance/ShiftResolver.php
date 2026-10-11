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
use App\Services\Settings\TenantSettings;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spec §7.3 resolveShift: latest effective assignment → tenant default shift, then overrides by priority.
 */
final class ShiftResolver
{
    public function __construct(private readonly ShiftOverrideApplier $applier) {}

    /**
     * @param  string|null  $firstPunchLocal  "HH:MM" of the day's first punch — used only for auto shift detection
     */
    public function resolve(Employee $employee, string $date, string $timezone, ?string $firstPunchLocal = null): ?ResolvedShift
    {
        $shift = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first()?->shift
            ?? $this->detect($firstPunchLocal)
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

    /**
     * Employees whose assigned shift on $date is $shiftId: their latest covering assignment, or the default shift when
     * they have none. Auto-detection and overrides are ignored — this filters lists, it does not compute a day.
     *
     * @return list<int>
     */
    public function employeeIdsOnShift(int $shiftId, string $date): array
    {
        return $this->employeeIdsOnShiftBetween($shiftId, $date, $date);
    }

    /**
     * Employees whose assigned shift is $shiftId on at least one day from $from to $to (Y-m-d), by the same rule as
     * employeeIdsOnShift(): the latest assignment covering that day, else the default shift.
     *
     * @return list<int>
     */
    public function employeeIdsOnShiftBetween(int $shiftId, string $from, string $to): array
    {
        $assignments = ShiftAssignment::query()
            ->where('effective_from', '<=', $to)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->get(['employee_id', 'shift_id', 'effective_from', 'effective_to'])
            ->groupBy('employee_id');
        $isDefault = Shift::query()->whereKey($shiftId)->where('is_default', true)->exists();
        $dates = collect(CarbonPeriod::create($from, $to))->map(fn ($d) => $d->format('Y-m-d'));

        $ids = [];
        foreach ($assignments as $employeeId => $list) {
            foreach ($dates as $date) {
                $current = $list->first(fn (ShiftAssignment $a) => $a->effective_from->format('Y-m-d') <= $date
                    && ($a->effective_to === null || $a->effective_to->format('Y-m-d') >= $date));
                if ($current !== null ? (int) $current->shift_id === $shiftId : $isDefault) {
                    $ids[] = (int) $employeeId;
                    break;
                }
            }
        }
        if ($isDefault) {
            $ids = array_merge($ids, Employee::query()->whereNotIn('id', $assignments->keys())->pluck('id')->all());
        }

        return array_values(array_unique($ids));
    }

    /**
     * Spec §9 phase 4: with `auto_shift_detection` on, an employee without an assignment gets the shift whose start
     * is nearest to their first punch, if within ±3 h (circular, so a 23:50 punch matches a 00:00 shift).
     */
    private function detect(?string $firstPunchLocal): ?Shift
    {
        if ($firstPunchLocal === null || ! app(TenantSettings::class)->get('auto_shift_detection')) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $firstPunchLocal));
        $punch = $h * 60 + $m;

        return Shift::query()->get()
            ->map(function (Shift $s) use ($punch) {
                [$sh, $sm] = array_map('intval', explode(':', substr((string) $s->start_time, 0, 5)));
                $diff = abs($punch - ($sh * 60 + $sm));

                return ['shift' => $s, 'diff' => min($diff, 1440 - $diff)];
            })
            ->filter(fn (array $c) => $c['diff'] <= 180)
            ->sortBy('diff')
            ->first()['shift'] ?? null;
    }
}
