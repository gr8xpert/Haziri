<?php

declare(strict_types=1);

namespace App\Filament\Central\Widgets;

use App\Enums\EmployeeStatus;
use App\Enums\TenantStatus;
use App\Filament\Central\Resources\Invoices\InvoiceResource;
use App\Filament\Central\Resources\Tenants\TenantResource;
use App\Filament\Central\Resources\UnknownDevices\UnknownDeviceResource;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Models\Employee;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/** Super-admin overview: companies, people on the platform, money and machines waiting for attention. */
class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $byStatus = Tenant::query()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $count = fn (TenantStatus $s) => (int) ($byStatus[$s->value] ?? 0);
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $billed = Invoice::query()->whereDate('period_start', $monthStart);
        $open = Invoice::query()->where('status', 'open');
        $overdue = (clone $open)->whereDate('due_on', '<', now()->format('Y-m-d'))->count();
        $toConfirm = Payment::query()->where('status', 'submitted')->count();
        $waiting = UnknownDeviceResource::getNavigationBadge();

        return [
            Stat::make('Companies', $byStatus->sum())->icon(Heroicon::OutlinedBuildingOffice2)
                ->description("{$count(TenantStatus::Active)} active · {$count(TenantStatus::Trial)} trial · {$count(TenantStatus::Suspended)} suspended")
                ->color($count(TenantStatus::Suspended) ? 'warning' : 'gray')->url(TenantResource::getUrl()),
            Stat::make('Active employees', number_format(self::activeEmployees()))->icon(Heroicon::OutlinedUsers)
                ->description('Across all companies (refreshed every 10 minutes)')->color('gray'),
            Stat::make('Billed this month', 'Rs ' . number_format((int) $billed->sum('amount')))->icon(Heroicon::OutlinedBanknotes)
                ->description($billed->count() . ' invoices for ' . now()->format('F'))->color('gray')->url(InvoiceResource::getUrl()),
            Stat::make('Unpaid', 'Rs ' . number_format((int) $open->sum('amount')))->icon(Heroicon::OutlinedExclamationTriangle)
                ->description($overdue ? "{$overdue} overdue" : 'Nothing overdue')->color($overdue ? 'danger' : 'gray')->url(InvoiceResource::getUrl()),
            Stat::make('Payments to confirm', $toConfirm)->icon(Heroicon::OutlinedClock)
                ->description($toConfirm ? 'Companies say they have paid' : 'None waiting')->color($toConfirm ? 'warning' : 'gray')
                ->url(InvoiceResource::getUrl()),
            Stat::make('Unknown machines', (int) $waiting)->icon(Heroicon::OutlinedCpuChip)
                ->description($waiting ? 'Waiting to be attached or blocked' : 'None waiting')->color($waiting ? 'warning' : 'gray')
                ->url(UnknownDeviceResource::getUrl()),
        ];
    }

    /** Each company has its own database, so the total is counted company by company and cached. */
    private static function activeEmployees(): int
    {
        return Cache::remember('central:active-employees', 600, fn () => (int) Tenant::query()->get()->sum(
            fn (Tenant $tenant) => $tenant->run(fn () => Employee::query()->where('status', '!=', EmployeeStatus::Left)->count()),
        ));
    }
}
