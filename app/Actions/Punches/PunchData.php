<?php

declare(strict_types=1);

namespace App\Actions\Punches;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use App\Enums\VerifyType;
use Carbon\CarbonImmutable;

/**
 * A punch from any source, normalised before ingestion. `punchedAt` must already be UTC.
 * Machine sources set devicePin (+ deviceId/deviceSerial); kiosk/mobile/manual set employeeId.
 */
final readonly class PunchData
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public CarbonImmutable $punchedAt,
        public PunchSource $source,
        public ?string $devicePin = null,
        public ?int $employeeId = null,
        public ?int $deviceId = null,
        public ?string $deviceSerial = null,
        public PunchDirection $direction = PunchDirection::Unknown,
        public VerifyType $verifyType = VerifyType::None,
        public ?string $workcode = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?int $accuracyM = null,
        public ?string $photoPath = null,
        public ?string $externalId = null,
        public bool $isManual = false,
        public ?string $reason = null,
        public ?int $enteredByUserId = null,
        public array $meta = [],
        /** Store as `pending` (e.g. out-of-geofence mobile punch) — not counted until HR approves. */
        public bool $requiresApproval = false,
    ) {}

    /** sha1(device_serial|device_pin|punched_at) — spec §5.4. Non-device sources use source:employee as the "serial". */
    public function dedupeHash(): string
    {
        $serial = $this->deviceSerial ?? ($this->source->value . ':' . ($this->deviceId ?? '-'));
        $pin = $this->devicePin ?? ('emp:' . $this->employeeId);

        return sha1($serial . '|' . $pin . '|' . $this->punchedAt->utc()->format('Y-m-d H:i:s'));
    }
}
