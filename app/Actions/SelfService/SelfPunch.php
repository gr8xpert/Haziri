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

    /** "auto" direction: odd number of valid punches today → this one is an out. */
    public static function nextDirection(int $employeeId): PunchDirection
    {
        $today = CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->format('Y-m-d');
        $count = Punch::query()->where('employee_id', $employeeId)->where('local_date', $today)
            ->whereIn('status', [PunchStatus::Valid, PunchStatus::Pending])->count();

        return $count % 2 === 1 ? PunchDirection::Out : PunchDirection::In;
    }
}
