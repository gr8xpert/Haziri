<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Current tenant's leave balances (run via tenants:run):
 *   default      — refresh accruals for the current leave year (scheduled on the 1st)
 *   --open-year  — open the leave year with carry-forward; scheduled on every 1st, it only acts in the
 *                  tenant's leave-year start month (setting leave_year_start_month) unless --force is given
 */
#[Signature('hazri:leave:balances {--open-year} {--force}')]
#[Description('Accrue leave, or open a new leave year with carry-forward')]
final class LeaveBalances extends Command
{
    public function handle(LeaveBalanceService $balances, LeaveYear $leaveYear): int
    {
        $year = $leaveYear->current();
        if ($this->option('open-year')) {
            $month = CarbonImmutable::now(tenant('timezone') ?? config('hazri.default_timezone'))->month;
            if ($month !== $leaveYear->startMonth() && ! $this->option('force')) {
                $this->info('Not the leave-year start month; nothing to open.');

                return self::SUCCESS;
            }
            $n = $balances->openYear($year);
        } else {
            $n = $balances->accrueAll($year);
        }
        $this->info("{$n} balance(s) updated for {$leaveYear->label($year)}.");

        return self::SUCCESS;
    }
}
