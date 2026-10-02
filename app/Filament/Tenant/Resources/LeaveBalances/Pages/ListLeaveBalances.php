<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LeaveBalances\Pages;

use App\Filament\Tenant\Resources\LeaveBalances\LeaveBalanceResource;
use App\Services\Leave\LeaveBalanceService;
use App\Services\Leave\LeaveYear;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLeaveBalances extends ListRecords
{
    protected static string $resource = LeaveBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label(__('Create / refresh balances'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn () => auth()->user()->can('leave.adjust_balance'))
                ->requiresConfirmation()
                ->modalDescription(__('Creates missing balances for this leave year (with carry-forward) and refreshes accruals and grade entitlements.'))
                ->action(function (LeaveBalanceService $balances, LeaveYear $leaveYear) {
                    $year = $leaveYear->current();
                    $n = $balances->openYear($year) + $balances->accrueAll($year);
                    Notification::make()->success()->title(__(':n balances updated', ['n' => $n]))->send();
                }),
        ];
    }
}
