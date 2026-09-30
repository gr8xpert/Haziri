<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ai\AnomalyDetector;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hazri:anomalies:detect')]
#[Description('Rule-based anomaly detection for the current tenant (spec §12.3)')]
final class DetectAnomalies extends Command
{
    public function handle(AnomalyDetector $detector): int
    {
        $n = $detector->run(CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone')));
        $this->info("{$n} new anomaly(ies).");

        return self::SUCCESS;
    }
}
