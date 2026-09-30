<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use Carbon\CarbonImmutable;

/** One valid punch as the engine sees it. `at` is UTC. */
final readonly class PunchInput
{
    public function __construct(
        public CarbonImmutable $at,
        public PunchDirection $direction = PunchDirection::Unknown,
        public PunchSource $source = PunchSource::Manual,
        public bool $isManual = false,
        public bool $geofenceFail = false,
    ) {}
}
