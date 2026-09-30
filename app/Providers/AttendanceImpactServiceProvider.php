<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\ShiftOverrideScope;
use App\Models\AttendanceDay;
use App\Models\AttendanceMarker;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftOverride;
use App\Services\Attendance\AttendanceImpact;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

/**
 * Spec §7.1 triggers: any attendance-affecting change queues a recompute of the affected employee-days.
 * Centralised here so Filament CRUD, actions and imports all get the same behaviour.
 */
final class AttendanceImpactServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->on(Holiday::class, function (Holiday $h) {
            foreach ($this->dates($h, 'date') as $date) {
                AttendanceImpact::dispatch(null, $date, $date, $h->branch_id);
            }
        });

        $this->on(ShiftAssignment::class, fn (ShiftAssignment $a) => AttendanceImpact::dispatch(
            [$a->employee_id],
            min($this->dates($a, 'effective_from')),
            $a->effective_to?->format('Y-m-d'),
        ));

        $this->on(ShiftOverride::class, function (ShiftOverride $o) {
            $ids = match ($o->scope) {
                ShiftOverrideScope::Branch => Employee::where('branch_id', $o->scope_id)->pluck('id')->all(),
                ShiftOverrideScope::Department => Employee::where('department_id', $o->scope_id)->pluck('id')->all(),
                default => null,
            };
            AttendanceImpact::dispatch($ids, min($this->dates($o, 'from_date')), max($this->dates($o, 'to_date')));
        });

        Shift::updated(function (Shift $s) {
            if (array_diff(array_keys($s->getChanges()), ['updated_at']) === []) {
                return;
            }
            AttendanceImpact::dispatch(null, now()->subDays(31)->format('Y-m-d'));
        });

        Employee::updated(function (Employee $e) {
            if ($e->wasChanged(['joined_on', 'left_on', 'branch_id', 'overtime_eligible', 'department_id'])) {
                AttendanceImpact::dispatch([$e->id], now()->subDays(AttendanceImpact::LOOKBACK_DAYS)->format('Y-m-d'));
            }
        });

        // Machine punches queue their own recompute on ingest; this covers later edits / deletes / restores.
        Punch::updated(fn (Punch $p) => $this->punchChanged($p));
        Punch::deleted(fn (Punch $p) => $this->punchChanged($p));
        Punch::restored(fn (Punch $p) => $this->punchChanged($p));

        $this->on(AttendanceMarker::class, fn (AttendanceMarker $m) => AttendanceImpact::dispatch(
            [$m->employee_id], min($this->dates($m, 'date')), max($this->dates($m, 'date')),
        ));

        $this->on(LeaveRequest::class, function (LeaveRequest $l) {
            if (! $l->wasRecentlyCreated && ! $l->wasChanged(['status', 'from_date', 'to_date', 'day_part', 'sandwich_dates']) && $l->exists) {
                return;
            }
            // Include the previous sandwich days too: a cancel clears them but they still need recomputing.
            $sandwich = array_merge($l->sandwich_dates ?? [], (array) ($l->getOriginal('sandwich_dates') ?? []));
            $from = min([...$this->dates($l, 'from_date'), ...$sandwich]);
            $to = max([...$this->dates($l, 'to_date'), ...$sandwich]);
            AttendanceImpact::dispatch([$l->employee_id], $from, $to);
        });

        AttendanceDay::updated(function (AttendanceDay $d) {
            if ($d->wasChanged('manual_override')) {
                AttendanceImpact::dispatch([$d->employee_id], $d->date->format('Y-m-d'), $d->date->format('Y-m-d'));
            }
        });
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function on(string $model, callable $handler): void
    {
        $model::saved($handler);
        $model::deleted($handler);
    }

    private function punchChanged(Punch $p): void
    {
        if ($p->employee_id === null) {
            return;
        }
        foreach ($this->dates($p, 'local_date') as $date) {
            AttendanceImpact::dispatch([$p->employee_id], $date, $date);
        }
        $prev = $p->local_date->copy()->subDay()->format('Y-m-d');
        AttendanceImpact::dispatch([$p->employee_id], $prev, $prev);
    }

    /**
     * Current and original value of a date attribute (a moved holiday affects both days).
     *
     * @return non-empty-list<string>
     */
    private function dates(Model $model, string $attribute): array
    {
        $current = $model->getAttribute($attribute);
        $dates = [$current instanceof CarbonInterface ? $current->format('Y-m-d') : substr((string) $current, 0, 10)];
        $original = $model->getOriginal($attribute);
        if ($original !== null) {
            $dates[] = $original instanceof CarbonInterface ? $original->format('Y-m-d') : substr((string) $original, 0, 10);
        }

        return array_values(array_unique(array_filter($dates)));
    }
}
