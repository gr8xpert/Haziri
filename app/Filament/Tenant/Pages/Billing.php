<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\NavigationGroup;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** Owner's billing page: invoices, how to pay, submit a payment reference for confirmation. */
class Billing extends Page
{
    protected string $view = 'filament.tenant.pages.billing';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string | \UnitEnum | null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 3;

    /** @var array<int, array{method?: string, reference?: string}> */
    public array $pay = [];

    public static function canAccess(): bool
    {
        return config('hazri.mode') !== 'dedicated' && (bool) auth()->user()?->can('billing.manage');
    }

    /** @return Collection<int, Invoice> */
    public function getInvoices(): Collection
    {
        return Invoice::query()->with('payments')->where('tenant_id', tenant('id'))->latest('id')->limit(24)->get();
    }

    public function submit(int $invoiceId): void
    {
        $invoice = Invoice::query()->where('tenant_id', tenant('id'))->where('status', 'open')->findOrFail($invoiceId);
        $data = validator($this->pay[$invoiceId] ?? [], [
            'method' => ['required', 'in:' . implode(',', array_keys(Payment::METHODS))],
            'reference' => ['required', 'string', 'max:100'],
        ])->validate();
        $invoice->payments()->create($data + ['amount' => $invoice->amount, 'submitted_by' => auth()->user()->email]);
        unset($this->pay[$invoiceId]);
        Notification::make()->success()->title(__('Thank you — we will confirm the payment shortly.'))->send();
    }
}
