<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\MultiPunchMode;
use App\Services\Attendance\Engine\ResolvedShift;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Shift extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $guarded = ['id'];

    /** A deleted shift keeps its code until a new or edited shift takes that code; then the old one gets a retired code. */
    protected static function booted(): void
    {
        static::saving(function (Shift $shift) {
            if (! $shift->isDirty('code')) {
                return;
            }
            static::onlyTrashed()->where('code', $shift->code)->whereKeyNot($shift->id ?? 0)->get()
                ->each(fn (Shift $old) => $old->forceFill(['code' => $old->retiredCode()])->saveQuietly());
        });
    }

    /** "MOR~7": the old code with the shift id, cut to fit the 20-character column. */
    public function retiredCode(): string
    {
        $suffix = '~' . $this->id;

        return mb_substr((string) $this->code, 0, 20 - strlen($suffix)) . $suffix;
    }

    /** Employees whose shift assignment points at this shift today or later (who fall back to the default if it is deleted). */
    public function employeesUsingCount(): int
    {
        $today = now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d');

        return ShiftAssignment::query()->where('shift_id', $this->id)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->whereHas('employee', fn ($q) => $q->where('status', '!=', EmployeeStatus::Left))
            ->distinct()->count('employee_id');
    }

    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'multi_punch_mode' => MultiPunchMode::class,
            'weekly_off_days' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    /**
     * @param  list<int>  $fallbackWeeklyOffDays  branch weekly offs, used when the shift has none
     */
    /** "Night (22:00–06:00)": the name, with the hours unless the name already shows them. */
    public function displayName(): string
    {
        if (preg_match('/\d{1,2}:\d{2}/', (string) $this->name)) {
            return (string) $this->name;
        }

        return sprintf('%s (%s–%s)', $this->name, substr((string) $this->start_time, 0, 5), substr((string) $this->end_time, 0, 5));
    }

    public function toResolved(array $fallbackWeeklyOffDays = [0]): ResolvedShift
    {
        return new ResolvedShift(
            shiftId: $this->id,
            startTime: substr((string) $this->start_time, 0, 5),
            endTime: substr((string) $this->end_time, 0, 5),
            crossesMidnight: $this->crosses_midnight,
            graceInMin: $this->grace_in_min,
            graceOutMin: $this->grace_out_min,
            lateAfterMin: $this->late_after_min,
            halfDayIfLateAfterMin: $this->half_day_if_late_after_min,
            halfDayIfWorkedLessThanMin: $this->half_day_if_worked_less_than_min,
            earlyOutBeforeMin: $this->early_out_before_min,
            autoCloseMissingOutAfterMin: $this->auto_close_missing_out_after_min,
            breakMin: $this->break_min,
            multiPunchMode: $this->multi_punch_mode,
            overtimeAfterMin: $this->overtime_after_min,
            maxOvertimeMin: $this->max_overtime_min,
            weeklyOffDays: array_map('intval', $this->weekly_off_days ?? $fallbackWeeklyOffDays),
        );
    }
}
