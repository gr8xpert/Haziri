<?php

declare(strict_types=1);

namespace App\Actions\Punches;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\Role;
use App\Models\AttendancePeriod;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Receptionist / HR manual entry (spec §6.4): an in time and optional out time for one employee-day.
 * Receptionists may only enter today or yesterday; HR/admin/owner any past date. Reason is mandatory. Never in a locked month.
 */
final class CreateManualPunches
{
    public function __construct(
        private readonly IngestPunchAction $ingest,
        private readonly TenantSettings $settings,
    ) {}

    /**
     * @return list<IngestResult>
     */
    public function __invoke(
        Employee $employee,
        string $date,
        ?string $inTime,
        ?string $outTime,
        string $reason,
        User $by,
        ?string $note = null,
        bool $replaceDay = false,
    ): array {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $this->authorise($date, $by, $tz, $reason, $inTime, $outTime);
        if ($replaceDay && ! $by->can('attendance.manual_edit')) {
            throw ValidationException::withMessages(['in_time' => __('Only HR can change a time that is already recorded. Add the missing time only, or ask HR.')]);
        }

        $results = [];
        $in = $inTime ? CarbonImmutable::parse("{$date} {$inTime}", $tz) : null;
        $out = $outTime ? CarbonImmutable::parse("{$date} {$outTime}", $tz) : null;
        if ($in !== null && $out !== null && $out->lessThanOrEqualTo($in)) {
            $out = $out->addDay(); // night shift: out time is on the next calendar day
        }

        $override = [];
        if ($replaceDay) {
            $this->removeEarlierOverride($employee, $date, $reason, $by);
            $override = ['override_for' => $date, 'override_id' => (string) Str::uuid()];
        }

        foreach ([[$in, PunchDirection::In], [$out, PunchDirection::Out]] as [$at, $direction]) {
            if ($at === null) {
                continue;
            }
            $results[] = ($this->ingest)(new PunchData(
                punchedAt: $at->utc(),
                source: PunchSource::Manual,
                employeeId: $employee->id,
                direction: $direction,
                isManual: true,
                reason: $reason,
                enteredByUserId: $by->id,
                meta: array_filter(['note' => $note]) + $override,
            ));
        }

        return $results;
    }

    /**
     * Manual Entry changed a time that was already there: the day's earlier manual punches (and an earlier override's
     * next-morning out) are removed with a reason. Machine, kiosk and phone punches stay but no longer count for the day,
     * because the new punches carry `override_for` and the engine then uses only them (StatusEngine::punchesInWindow).
     */
    private function removeEarlierOverride(Employee $employee, string $date, string $reason, User $by): void
    {
        $earlier = Punch::query()->where('employee_id', $employee->id)->where('is_manual', true)
            ->where(fn ($q) => $q->whereDate('local_date', $date)->orWhere('meta->override_for', $date))
            ->get();
        foreach ($earlier as $punch) {
            $punch->update(['meta' => array_merge($punch->meta ?? [], [
                'removed_reason' => __('Replaced in Manual Entry: :reason', ['reason' => $reason]), 'removed_by' => $by->id,
            ])]);
            $punch->delete();
        }
    }

    private function authorise(string $date, User $by, string $tz, string $reason, ?string $in, ?string $out): void
    {
        $errors = [];
        $today = CarbonImmutable::now($tz)->startOfDay();
        $day = CarbonImmutable::parse($date, $tz);

        if (trim($reason) === '') {
            $errors['reason'] = __('A reason is required for manual entries.');
        }
        if ($in === null && $out === null) {
            $errors['in_time'] = __('Enter an in time, an out time, or both.');
        }
        if ($day->greaterThan($today) && ! $this->settings->get('allow_future_manual_punch')) {
            $errors['date'] = __('Manual punches cannot be entered for future dates.');
        }
        if (AttendancePeriod::isLocked($day->format('Y-m-d'))) {
            $errors['date'] = __('That month is locked.');
        }
        if (! $by->can('attendance.manual_edit') && $day->lessThan($today->subDay())) {
            $errors['date'] = __('Receptionists can only enter punches for today or yesterday.');
        }
        if (! $by->can('attendance.manual_entry') && ! $by->hasRole(Role::Owner->value)) {
            $errors['employee'] = __('You are not allowed to enter punches.');
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
