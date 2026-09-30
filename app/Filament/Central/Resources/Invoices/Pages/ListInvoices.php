<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Invoices\Pages;

use App\Filament\Central\Resources\Invoices\InvoiceResource;
use App\Services\Billing\BillingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')->label('Invoice this month')->requiresConfirmation()
                ->action(fn (BillingService $b) => Notification::make()->success()
                    ->title($b->invoiceMonth(now(config('hazri.default_timezone'))->startOfMonth()->toImmutable()) . ' invoice(s) created')->send()),
        ];
    }
}
