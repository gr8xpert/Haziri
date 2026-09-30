<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShiftOverrideScope;
use App\Services\Attendance\Engine\ShiftOverrideInput;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ShiftOverride extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    /** DB column => ResolvedShift property */
    private const FIELD_MAP = [
        'start_time' => 'startTime',
        'end_time' => 'endTime',
        'grace_in_min' => 'graceInMin',
        'grace_out_min' => 'graceOutMin',
        'late_after_min' => 'lateAfterMin',
        'half_day_if_late_after_min' => 'halfDayIfLateAfterMin',
        'half_day_if_worked_less_than_min' => 'halfDayIfWorkedLessThanMin',
        'break_min' => 'breakMin',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date:Y-m-d',
            'to_date' => 'date:Y-m-d',
            'scope' => ShiftOverrideScope::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function toInput(): ShiftOverrideInput
    {
        $changes = [];
        foreach (self::FIELD_MAP as $column => $property) {
            $value = $this->{$column};
            if ($value === null) {
                continue;
            }
            $changes[$property] = in_array($column, ['start_time', 'end_time'], true) ? substr((string) $value, 0, 5) : (int) $value;
        }

        return new ShiftOverrideInput($this->id, (int) $this->priority, $changes, $this->weekday);
    }
}
