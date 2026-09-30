<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Leave\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Current tenant's leave balances (run via tenants:run):
 *   default      — refresh monthly accruals for this year (scheduled on the 1st)
 *   --open-year  — open this year's balances with carry-forward (scheduled on 1 January)
 */
#[Signature('hazri:leave:balances {--open-year}')]
#[Description('Accrue monthly leave, or open a new leave year with carry-forward')]
final class LeaveBalances extends Command
{
    public function handle(LeaveBalanceService $balances): int
    {
        $year = CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->year;
        $n = $this->option('open-year') ? $balances->openYear($year) : $balances->accrueAll($year);
        $this->info("{$n} balance(s) updated for {$year}.");

        return self::SUCCESS;
    }
}
