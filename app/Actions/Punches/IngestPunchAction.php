<?php

declare(strict_types=1);

namespace App\Actions\Punches;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Jobs\RecomputeAttendanceDay;
use App\Models\Employee;
use App\Models\Punch;
use App\Models\UnmappedPin;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * Every punch source ends here (spec §6): normalise → dedupe → map PIN → store → queue recompute.
 * Sources never write attendance_days.
 */
final class IngestPunchAction
{
    private const MACHINE_SOURCES = [PunchSource::Adms, PunchSource::Agent, PunchSource::Usb];

    public function __construct(private readonly TenantSettings $settings) {}

    public function __invoke(PunchData $data): IngestResult
    {
        $hash = $data->dedupeHash();
        if (Punch::withTrashed()->where('dedupe_hash', $hash)->exists()) {
            return new IngestResult(IngestOutcome::AlreadyStored, null);
        }

        $employee = $this->mapEmployee($data);
        $tz = tenant('timezone') ?? config('hazri.default_timezone');
        $localDate = $data->punchedAt->setTimezone($tz)->format('Y-m-d');

        $direction = $data->direction;
        if (in_array($data->source, self::MACHINE_SOURCES, true) && ! $this->settings->get('trust_device_direction')) {
            $direction = PunchDirection::Unknown;
        }

        $status = match (true) {
            $employee === null => PunchStatus::Unmapped,
            $this->isDoubleTap($employee->id, $data->punchedAt) => PunchStatus::Duplicate,
            $data->requiresApproval => PunchStatus::Pending,
            default => PunchStatus::Valid,
        };

        try {
            $punch = Punch::create([
                'employee_id' => $employee?->id,
                'device_pin' => $data->devicePin ?? $employee?->device_pin,
                'device_id' => $data->deviceId,
                'punched_at' => $data->punchedAt->utc()->format('Y-m-d H:i:s'),
                'local_date' => $localDate,
                'direction' => $direction,
                'source' => $data->source,
                'verify_type' => $data->verifyType,
                'workcode' => $data->workcode,
                'lat' => $data->lat,
                'lng' => $data->lng,
                'accuracy_m' => $data->accuracyM,
                'photo_path' => $data->photoPath,
                'external_id' => $data->externalId,
                'dedupe_hash' => $hash,
                'is_manual' => $data->isManual,
                'reason' => $data->reason,
                'entered_by_user_id' => $data->enteredByUserId,
                'status' => $status,
                'meta' => $data->meta ?: null,
            ]);
        } catch (QueryException $e) {
            // Two workers raced on the same punch: the unique dedupe_hash index is the real guard.
            if (str_contains($e->getMessage(), 'dedupe_hash') || $e->getCode() === '23000') {
                return new IngestResult(IngestOutcome::AlreadyStored, null);
            }
            throw $e;
        }

        if ($status === PunchStatus::Unmapped) {
            $this->recordUnmapped($data);

            return new IngestResult(IngestOutcome::Unmapped, $punch);
        }
        if ($status === PunchStatus::Valid) {
            self::queueRecompute($employee->id, $localDate);
        }

        return new IngestResult(match ($status) {
            PunchStatus::Valid => IngestOutcome::Stored,
            PunchStatus::Pending => IngestOutcome::PendingApproval,
            default => IngestOutcome::DoubleTap,
        }, $punch);
    }

    /** The punch's own day plus the previous one (a 06:00 punch may close last night's shift). */
    public static function queueRecompute(int $employeeId, string $localDate): void
    {
        RecomputeAttendanceDay::debounced($employeeId, $localDate);
        RecomputeAttendanceDay::debounced($employeeId, CarbonImmutable::parse($localDate)->subDay()->format('Y-m-d'));
    }

    private function mapEmployee(PunchData $data): ?Employee
    {
        if ($data->employeeId !== null) {
            return Employee::query()->find($data->employeeId);
        }
        if ($data->devicePin === null || $data->devicePin === '') {
            return null;
        }

        return Employee::query()->where('device_pin', $data->devicePin)->first();
    }

    private function isDoubleTap(int $employeeId, CarbonImmutable $at): bool
    {
        $window = (int) $this->settings->get('dedupe_window_sec', 60);
        if ($window <= 0) {
            return false;
        }

        return Punch::query()
            ->where('employee_id', $employeeId)
            ->where('status', PunchStatus::Valid)
            ->whereBetween('punched_at', [
                $at->subSeconds($window - 1)->utc()->format('Y-m-d H:i:s'),
                $at->addSeconds($window - 1)->utc()->format('Y-m-d H:i:s'),
            ])
            ->exists();
    }

    private function recordUnmapped(PunchData $data): void
    {
        $row = UnmappedPin::query()->firstOrNew(['device_id' => $data->deviceId, 'device_pin' => (string) $data->devicePin]);
        $row->first_seen_at ??= CarbonImmutable::now('UTC');
        $row->count = ($row->count ?? 0) + 1;
        $row->resolved_employee_id = null;
        $row->save();
    }
}
