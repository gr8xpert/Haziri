<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Demo\DemoDay;
use App\Support\DemoMode;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Keeps the public demo company's "today" moving: punches, kiosk photos, phone punches and visitors whose time has
 * come are added, so the dashboard looks like a real working day at any hour. Only the demo company.
 */
#[Signature('hazri:demo:live')]
#[Description('Play the public demo company\'s working day up to now (every 10 minutes)')]
final class PlayDemoDay extends Command
{
    public function handle(DemoDay $day): int
    {
        $slug = (string) config('hazri.demo.tenant');
        $tenant = DemoMode::isDemoSlug($slug) ? Tenant::query()->where('slug', $slug)->first() : null;
        if ($tenant === null) {
            return self::SUCCESS; // no demo, or it is being rebuilt right now
        }

        $updated = $tenant->run(fn () => $day->catchUp(CarbonImmutable::now($tenant->timezone ?? config('hazri.default_timezone'))));
        $this->line("{$updated} employee day(s) updated.");

        return self::SUCCESS;
    }
}
