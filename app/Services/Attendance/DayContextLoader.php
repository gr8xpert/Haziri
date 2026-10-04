<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\ExemptionType;
use App\Enums\PunchStatus;
use App\Enums\RequestStatus;
use App\Models\AttendanceDay;
use App\Models\AttendanceExemption;
use App\Models\AttendanceMarker;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Services\Attendance\Engine\DayContext;
use App\Services\Attendance\Engine\EngineSettings;
use App\Services\Attendance\Engine\LeaveInput;
use App\Services\Attendance\Engine\PunchInput;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Builds the engine's DayContext for one employee-day from the tenant DB. */
final class DayContextLoader
{
    public function __construct(
        private readonly ShiftResolver $shifts,
        private readonly TenantSettings $settings,
    ) {}

    public function load(Employee $employee, string $date, CarbonImmutable $now): DayContext
    {
        $timezone = $this->timezoneFor($employee);

        // Wide enough for any shift window incl. night shifts; the engine narrows it down.
        $from = CarbonImmutable::parse($date, $timezone)->subDay()->utc();
        $to = CarbonImmutable::parse($date, $timezone)->addDays(2)->utc();
        $punches = Punch::query()
            ->where('employee_id', $employee->id)
            ->where('status', PunchStatus::Valid)
            ->whereBetween('punched_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->orderBy('punched_at')
            ->get()
            ->map(fn (Punch $p) => $p->toInput())
            ->all();

        $holiday = Holiday::query()->appliesTo($employee, $date)->first();

        $marker = AttendanceMarker::query()->where('employee_id', $employee->id)->whereDate('date', $date)->first();
        $existing = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('date', $date)->first();
        $override = $existing?->manual_override['status'] ?? null;

        return new DayContext(
            date: $date,
            timezone: $timezone,
            now: $now,
            joinedOn: $employee->joined_on?->format('Y-m-d'),
            leftOn: $employee->left_on?->format('Y-m-d'),
            shift: $this->shifts->resolve($employee, $date, $timezone, $this->firstPunchLocal($punches, $date, $timezone)),
            punches: $punches,
            holidayId: $holiday?->id,
            isHoliday: $holiday !== null,
            holidayPaid: $holiday === null || $holiday->is_paid,
            leave: $this->leaveFor($employee, $date),
            marker: $marker?->status,
            manualOverride: $override !== null ? AttendanceStatus::from($override) : null,
            overtimeEligible: $employee->overtime_eligible,
            settings: EngineSettings::fromArray($this->settings->all()),
            exemption: $this->exemptionFor($employee, $date),
        );
    }

    /** @param list<PunchInput> $punches */
    private function firstPunchLocal(array $punches, string $date, string $timezone): ?string
    {
        foreach ($punches as $p) { // already ordered by time
            $local = $p->at->setTimezone($timezone);
            if ($local->format('Y-m-d') === $date) {
                return $local->format('H:i');
            }
        }

        return null;
    }

    public function timezoneFor(Employee $employee): string
    {
        return $employee->branch?->timezone ?? tenant('timezone') ?? config('hazri.default_timezone');
    }

    /** A whole-day exemption beats a late-only one when both cover the employee. */
    private function exemptionFor(Employee $employee, string $date): ?ExemptionType
    {
        $types = AttendanceExemption::query()->covering($employee, $date)->pluck('type');

        return $types->contains(ExemptionType::Absent) ? ExemptionType::Absent : $types->first();
    }

    private function leaveFor(Employee $employee, string $date): ?LeaveInput
    {
        $request = LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', RequestStatus::Approved)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $r) => $r->where('from_date', '<=', $date)->where('to_date', '>=', $date))
                ->orWhereJsonContains('sandwich_dates', $date))
            ->orderByDesc('id')
            ->first();

        if ($request === null) {
            return null;
        }

        return new LeaveInput(
            leaveRequestId: $request->id,
            isPaid: (bool) $request->leaveType?->is_paid,
            dayPart: $request->day_part,
            sandwich: in_array($date, $request->sandwich_dates ?? [], true),
            isShortLeave: $request->short_leave_from !== null,
        );
    }
}
