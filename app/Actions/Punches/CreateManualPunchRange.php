<?php

declare(strict_types=1);

namespace App\Actions\Punches;

use App\Enums\PunchStatus;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\User;
use App\Services\Attendance\WorkingDayCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

/**
 * Manual attendance for one employee over a date range (legacy "Generate"): the same in/out on every day,
 * through CreateManualPunches so each day keeps the usual rules and audit trail. Days that already have
 * punches, fall in a locked month, or (by default) are weekly offs / holidays are skipped and reported.
 */
final class CreateManualPunchRange
{
    public const MAX_DAYS = 62;

    public function __construct(
        private readonly CreateManualPunches $create,
        private readonly WorkingDayCalendar $calendar,
    ) {}

    /**
     * @return array{saved: list<string>, skipped: array<string, string>} dates saved; date => why skipped
     */
    public function __invoke(
        Employee $employee,
        string $from,
        string $to,
        ?string $inTime,
        ?string $outTime,
        string $reason,
        User $by,
        bool $workingDaysOnly = true,
    ): array {
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => __('The end date must be on or after the start date.')]);
        }
        $period = CarbonPeriod::create($from, $to);
        if ($period->count() > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => __('At most :n days at a time.', ['n' => self::MAX_DAYS])]);
        }
        $working = $workingDaysOnly ? array_flip($this->calendar->workingDates($employee, $from, $to)) : null;
        $punched = Punch::query()->where('employee_id', $employee->id)->where('status', PunchStatus::Valid)
            ->whereBetween('local_date', [$from, $to])->pluck('local_date')
            ->map(fn ($d) => CarbonImmutable::parse($d)->format('Y-m-d'))->flip();

        $saved = [];
        $skipped = [];
        foreach ($period as $day) {
            $date = $day->format('Y-m-d');
            $why = match (true) {
                $working !== null && ! isset($working[$date]) => __('weekly off / holiday / leave'),
                isset($punched[$date]) => __('already has punches'),
                AttendancePeriod::isLocked($date) => __('month is locked'),
                default => null,
            };
            if ($why !== null) {
                $skipped[$date] = $why;

                continue;
            }
            try {
                ($this->create)($employee, $date, $inTime, $outTime, $reason, $by);
                $saved[] = $date;
            } catch (ValidationException $e) {
                if ($saved === [] && $skipped === [] && $day->equalTo($period->getStartDate())) {
                    throw $e; // the input itself is invalid (no reason, no times, no permission)
                }
                $skipped[$date] = implode(' ', $e->validator->errors()->all());
            }
        }

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    /**
     * Remove the manual punches of one employee in a range (legacy "Delete"). Machine punches are never touched.
     *
     * @return int punches removed
     */
    public function remove(Employee $employee, string $from, string $to, string $reason, User $by): int
    {
        if (! $by->can('attendance.manual_edit')) {
            throw ValidationException::withMessages(['employee' => __('Only HR can remove manual punches over a range.')]);
        }
        $n = 0;
        foreach (Punch::query()->where('employee_id', $employee->id)->where('is_manual', true)
            ->whereBetween('local_date', [$from, $to])->get() as $punch) {
            if (AttendancePeriod::isLocked($punch->local_date->format('Y-m-d'))) {
                continue;
            }
            $punch->update(['meta' => array_merge($punch->meta ?? [], ['removed_reason' => $reason, 'removed_by' => $by->id])]);
            $punch->delete();
            $n++;
        }

        return $n;
    }
}
