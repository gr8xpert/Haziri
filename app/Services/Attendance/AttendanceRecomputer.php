<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceFlag;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Services\Attendance\Engine\StatusEngine;
use App\Services\Notifications\LateArrivalAlert;
use App\Services\Webhooks\Webhooks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The only writer of attendance_days (spec §15). Loads context → runs the engine → upserts the row.
 */
final class AttendanceRecomputer
{
    /** Bump when engine rules change so stale rows can be found and recomputed. */
    public const ENGINE_VERSION = 1;

    public function __construct(
        private readonly DayContextLoader $loader,
        private readonly StatusEngine $engine,
        private readonly LateArrivalAlert $lateArrivalAlert,
    ) {}

    /**
     * @return AttendanceDay|null null when skipped (locked period / locked row / unknown employee)
     */
    public function recompute(int $employeeId, string $date, ?CarbonImmutable $now = null): ?AttendanceDay
    {
        if (AttendancePeriod::isLocked($date)) {
            Log::info('Recompute skipped: period locked', ['tenant' => tenant('id'), 'employee_id' => $employeeId, 'date' => $date]);

            return null;
        }

        $employee = Employee::query()->with('branch')->find($employeeId);
        if ($employee === null) {
            return null;
        }

        $day = AttendanceDay::query()->firstOrNew(['employee_id' => $employeeId, 'date' => $date]);
        if ($day->is_locked) {
            return null;
        }

        $now ??= CarbonImmutable::now('UTC');
        $wasLate = in_array(AttendanceFlag::Late->value, $day->flags ?? [], true);
        $result = $this->engine->resolve($this->loader->load($employee, $date, $now));

        $day->fill([
            'status' => $result->status,
            'shift_id' => $result->shiftId,
            'override_id' => $result->overrideId,
            'holiday_id' => $result->holidayId,
            'leave_request_id' => $result->leaveRequestId,
            'first_in' => $result->firstIn?->format('Y-m-d H:i:s'),
            'last_out' => $result->lastOut?->format('Y-m-d H:i:s'),
            'worked_min' => $result->workedMin,
            'break_min' => $result->breakMin,
            'late_min' => $result->lateMin,
            'early_out_min' => $result->earlyOutMin,
            'overtime_min' => $result->overtimeMin,
            'expected_min' => $result->expectedMin,
            'flags' => $result->flagValues(),
            'source_summary' => $result->sourceSummary,
            'computed_at' => CarbonImmutable::now('UTC'),
            'computed_version' => self::ENGINE_VERSION,
        ]);
        $changed = $day->isDirty(['status', 'flags', 'first_in', 'last_out', 'late_min', 'overtime_min']);
        $day->save();
        if ($changed) {
            Webhooks::dispatch('attendance_day.updated', [
                'employee' => $employee->code, 'date' => $date, 'status' => $day->status?->value, 'flags' => $day->flags,
                'worked_min' => $day->worked_min, 'late_min' => $day->late_min, 'overtime_min' => $day->overtime_min,
            ]);
        }
        ($this->lateArrivalAlert)($employee, $day, $wasLate, $now);

        return $day;
    }
}
