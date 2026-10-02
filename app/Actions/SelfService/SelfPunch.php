<?php

declare(strict_types=1);

namespace App\Actions\SelfService;

use App\Actions\Punches\IngestPunchAction;
use App\Actions\Punches\IngestResult;
use App\Actions\Punches\PunchData;
use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\PunchStatus;
use App\Enums\VerifyType;
use App\Models\Employee;
use App\Models\Punch;
use App\Services\SelfService\GeoFence;
use App\Services\SelfService\PunchPhotoStore;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Mobile self-punch from /me (spec §6.6): GPS + accuracy + selfie. Inside the geofence → valid;
 * outside → stored as pending (flag geofence_fail) for HR approval. One punch per 60 s per employee.
 */
final class SelfPunch
{
    public function __construct(
        private readonly IngestPunchAction $ingest,
        private readonly GeoFence $fence,
        private readonly PunchPhotoStore $photos,
        private readonly TenantSettings $settings,
    ) {}

    /** @return array{result: IngestResult, direction: PunchDirection, geofence: array<string, mixed>} */
    public function __invoke(Employee $employee, float $lat, float $lng, ?int $accuracyM, ?string $photo, ?PunchDirection $direction = null): array
    {
        if (! $this->settings->featureEnabled('mobile_punch')) {
            throw ValidationException::withMessages(['punch' => __('Mobile punching is not enabled for your company.')]);
        }
        $now = CarbonImmutable::now('UTC');
        if (Punch::query()->where('employee_id', $employee->id)->where('source', PunchSource::Mobile)
            ->where('punched_at', '>', $now->subSeconds(60)->format('Y-m-d H:i:s'))->exists()) {
            throw ValidationException::withMessages(['punch' => __('You just punched. Please wait a minute.')]);
        }

        $geo = $this->fence->check($employee, $lat, $lng, $accuracyM);
        $direction ??= self::nextDirection($employee->id);

        $result = ($this->ingest)(new PunchData(
            punchedAt: $now,
            source: PunchSource::Mobile,
            employeeId: $employee->id,
            deviceSerial: 'mobile',
            direction: $direction,
            verifyType: $photo ? VerifyType::Photo : VerifyType::None,
            lat: $lat,
            lng: $lng,
            accuracyM: $accuracyM,
            photoPath: $this->photos->store($photo),
            meta: ['geofence_fail' => ! $geo['inside'], 'geofence' => $geo],
            requiresApproval: ! $geo['inside'],
        ));

        return ['result' => $result, 'direction' => $direction, 'geofence' => $geo];
    }

    /** An "in" older than this no longer counts as open, so a forgotten punch-out never makes the next arrival an "out". */
    public const SESSION_HOURS = 16;

    /**
     * "Auto" direction: the opposite of the employee's last punch in the past SESSION_HOURS (crosses midnight, so night
     * shifts work). Machine punches without a direction fall back to alternating within that window. No recent punch = in.
     */
    public static function nextDirection(int $employeeId, ?CarbonImmutable $at = null): PunchDirection
    {
        $at = ($at ?? CarbonImmutable::now('UTC'))->utc();
        $recent = Punch::query()->where('employee_id', $employeeId)
            ->whereIn('status', [PunchStatus::Valid, PunchStatus::Pending])
            ->whereBetween('punched_at', [$at->subHours(self::SESSION_HOURS)->format('Y-m-d H:i:s'), $at->format('Y-m-d H:i:s')])
            ->orderByDesc('punched_at')->orderByDesc('id')
            ->pluck('direction');

        return match ($recent->first()) {
            null => PunchDirection::In,
            PunchDirection::In => PunchDirection::Out,
            PunchDirection::Out => PunchDirection::In,
            default => $recent->count() % 2 === 1 ? PunchDirection::Out : PunchDirection::In,
        };
    }
}
