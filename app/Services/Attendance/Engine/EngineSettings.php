<?php

declare(strict_types=1);

namespace App\Services\Attendance\Engine;

/** Tenant rule parameters the day engine needs (subset of spec §7.4). */
final readonly class EngineSettings
{
    public function __construct(
        public int $dedupeWindowSec = 60,
        public bool $overtimeEnabled = false,
        public int $overtimeRoundingMin = 15,
        public int $punchSearchWindowBeforeMin = 240,
        public int $punchSearchWindowAfterMin = 360,
        public int $autoAbsentAfterDays = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $settings  key => value from the tenant `settings` table
     */
    public static function fromArray(array $settings): self
    {
        return new self(
            dedupeWindowSec: (int) ($settings['dedupe_window_sec'] ?? 60),
            overtimeEnabled: (bool) ($settings['overtime_enabled'] ?? false),
            overtimeRoundingMin: (int) ($settings['overtime_rounding_min'] ?? 15),
            punchSearchWindowBeforeMin: (int) ($settings['punch_search_window_before_min'] ?? 240),
            punchSearchWindowAfterMin: (int) ($settings['punch_search_window_after_min'] ?? 360),
            autoAbsentAfterDays: (int) ($settings['auto_absent_after_days'] ?? 1),
        );
    }
}
