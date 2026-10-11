<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ai\AnomalyDetector;
use App\Services\Settings\TenantSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hazri:anomalies:detect')]
#[Description('Rule-based anomaly detection for the current tenant (spec §12.3)')]
final class DetectAnomalies extends Command
{
    public function handle(AnomalyDetector $detector, TenantSettings $settings): int
    {
        // Anomaly detection is part of the "AI assistant & anomaly detection" feature; its page is hidden while it is off.
        if (! $settings->featureEnabled('ai_reports')) {
            $this->info('Anomaly detection is off for this company.');

            return self::SUCCESS;
        }
        $n = $detector->run(CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone')));
        $this->info("{$n} new anomaly(ies).");

        return self::SUCCESS;
    }
}
