<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\LeaveBalances\Pages;

use App\Filament\Tenant\Resources\LeaveBalances\LeaveBalanceResource;
use App\Services\Leave\LeaveBalanceService;
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
                ->modalDescription(__('Creates missing balances for this year (with carry-forward) and refreshes monthly accruals.'))
                ->action(function (LeaveBalanceService $balances) {
                    $n = $balances->openYear(now()->year) + $balances->accrueAll(now()->year);
                    Notification::make()->success()->title(__(':n balances updated', ['n' => $n]))->send();
                }),
        ];
    }
}
