<?php

declare(strict_types=1);

namespace App\Actions\Punches;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Receptionist / HR manual entry (spec §6.4): an in time and optional out time for one employee-day.
 * Receptionists may only enter today or yesterday; HR/admin/owner any past date. Reason is mandatory.
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
    ): array {
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $this->authorise($date, $by, $tz, $reason, $inTime, $outTime);

        $results = [];
        $in = $inTime ? CarbonImmutable::parse("{$date} {$inTime}", $tz) : null;
        $out = $outTime ? CarbonImmutable::parse("{$date} {$outTime}", $tz) : null;
        if ($in !== null && $out !== null && $out->lessThanOrEqualTo($in)) {
            $out = $out->addDay(); // night shift: out time is on the next calendar day
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
                meta: array_filter(['note' => $note]),
            ));
        }

        return $results;
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
