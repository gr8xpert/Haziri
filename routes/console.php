<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec §7.1: nightly recompute of the last 3 days for every tenant (catches late USB imports).
Schedule::command('tenants:run hazri:attendance:recompute-recent')->dailyAt('02:00')->withoutOverlapping();

// Spec §6.1: devices silent for 15 min are marked offline (once per outage); daily time sync.
Schedule::command('tenants:run hazri:devices:check')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('tenants:run hazri:devices:check --option="sync-time=1"')->dailyAt('03:00');
