<?php

declare(strict_types=1);

namespace App\Models;

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
            minFullDayMin: $this->min_full_day_min,
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
