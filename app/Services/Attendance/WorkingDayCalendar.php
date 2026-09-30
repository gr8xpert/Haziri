<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

/** Is a date a working day for an employee? (weekly off from the resolved shift, or a holiday) */
final class WorkingDayCalendar
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(private readonly ShiftResolver $shifts) {}

    public function isNonWorkingDay(Employee $employee, string $date): bool
    {
        $key = $employee->id . '|' . $date;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $tz = $employee->branch?->timezone ?? tenant('timezone') ?? config('hazri.default_timezone');
        $shift = $this->shifts->resolve($employee, $date, $tz);
        $weekday = (int) CarbonImmutable::parse($date, $tz)->dayOfWeek;
        $isOff = in_array($weekday, $shift?->weeklyOffDays ?? [0], true);
        $isHoliday = Holiday::query()->whereDate('date', $date)
            ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id))->exists();

        return $this->cache[$key] = $isOff || $isHoliday;
    }

    /** @return list<string> working dates in [from, to] */
    public function workingDates(Employee $employee, string $from, string $to): array
    {
        $dates = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $date = $d->format('Y-m-d');
            if (! $this->isNonWorkingDay($employee, $date)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }
}
