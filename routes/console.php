<?php

use App\Models\Central\UnknownDevice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Spec §7.1: nightly recompute of the last 3 days (or "Mark absent after" + 1) for every tenant (catches late USB imports).
Schedule::command('tenants:run hazri:attendance:recompute-recent')->dailyAt('02:00')->withoutOverlapping();

// Spec §6.1: devices silent for 15 min are marked offline (once per outage); daily time sync.
Schedule::command('tenants:run hazri:devices:check')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('tenants:run hazri:devices:check --option="sync-time=1"')->dailyAt('03:00');

// Leave: new leave year (carry-forward) on the 1st of each tenant's start month, then monthly accrual.
Schedule::command('tenants:run hazri:leave:balances --option="open-year=1"')->monthlyOn(1, '00:30');
Schedule::command('tenants:run hazri:leave:balances')->monthlyOn(1, '01:00');

// Spec §11: time-based notifications (absent at 11:00, missing punch-out, daily summary, HR reminders).
Schedule::command('tenants:run hazri:notify:scheduled')->everyFifteenMinutes()->withoutOverlapping();

// Spec §12.3: nightly rule-based anomaly scan (AI only phrases the explanations).
Schedule::command('tenants:run hazri:anomalies:detect')->dailyAt('06:00');

// Billing (central): invoices on the 1st, suspension check daily.
Schedule::command('hazri:billing --invoice')->monthlyOn(1, '07:00');
Schedule::command('hazri:billing --enforce')->dailyAt('07:30');

// "Keep photos" (photo_retention_days): punch photos older than that are deleted; 0 keeps them forever.
Schedule::command('tenants:run hazri:photos:prune')->dailyAt('04:30')->withoutOverlapping();

// Unknown ADMS machines not seen for 30 days (blocked ones are kept).
Schedule::command('model:prune', ['--model' => [UnknownDevice::class]])->dailyAt('04:00');

// The public demo company (HAZRI_DEMO_TENANT) is rebuilt every night (visitors' changes are gone by morning), and its
// working day is played out as the clock passes, so "today" always looks real.
Schedule::command('hazri:demo:reset')->dailyAt('03:30')->withoutOverlapping()->when(fn () => filled(config('hazri.demo.tenant')));
Schedule::command('hazri:demo:live')->everyTenMinutes()->withoutOverlapping()->when(fn () => filled(config('hazri.demo.tenant')));
