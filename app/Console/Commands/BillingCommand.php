<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Billing\BillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Central (not per tenant): --invoice on the 1st, --enforce daily. */
#[Signature('hazri:billing {--invoice} {--enforce}')]
#[Description('Generate monthly invoices and/or suspend tenants past due + grace')]
final class BillingCommand extends Command
{
    public function handle(BillingService $billing): int
    {
        $now = CarbonImmutable::now(config('hazri.default_timezone'));
        if ($this->option('invoice')) {
            $this->info($billing->invoiceMonth($now->startOfMonth()) . ' invoice(s) created.');
        }
        if ($this->option('enforce')) {
            $this->info($billing->enforce($now) . ' tenant(s) suspended for non-payment.');
        }

        return self::SUCCESS;
    }
}
