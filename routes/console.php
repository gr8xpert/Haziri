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

// Leave: monthly accrual on the 1st; new leave year (carry-forward) on 1 January.
Schedule::command('tenants:run hazri:leave:balances')->monthlyOn(1, '01:00');
Schedule::command('tenants:run hazri:leave:balances --option="open-year=1"')->yearlyOn(1, 1, '00:30');

// Spec §11: time-based notifications (absent at 11:00, missing punch-out, daily summary, HR reminders).
Schedule::command('tenants:run hazri:notify:scheduled')->everyFifteenMinutes()->withoutOverlapping();
