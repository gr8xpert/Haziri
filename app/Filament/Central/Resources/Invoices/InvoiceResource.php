<?php

declare(strict_types=1);

namespace App\Filament\Central\Resources\Invoices;

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Services\Billing\BillingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Billing (spec §9 phase 4): invoices, submitted payment references, manual confirmation. */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('tenant.company_name')->label('Company')->searchable(),
                TextColumn::make('period_start')->date('M Y')->label('Month'),
                TextColumn::make('employee_count')->label('Employees'),
                TextColumn::make('amount')->prefix('Rs ')->numeric(),
                TextColumn::make('due_on')->date(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'paid' => 'success', 'open' => 'warning', default => 'gray',
                }),
                TextColumn::make('payments_count')->counts(['payments' => fn ($q) => $q->where('status', 'submitted')])->label('To confirm'),
            ])
            ->filters([SelectFilter::make('status')->options(['open' => 'open', 'paid' => 'paid', 'void' => 'void'])])
            ->recordActions([
                Action::make('confirm')->label('Record / confirm payment')->icon(Heroicon::OutlinedShieldCheck)->color('success')
                    ->visible(fn (Invoice $r) => $r->status === 'open')
                    ->fillForm(fn (Invoice $r) => ($p = $r->payments()->where('status', 'submitted')->latest()->first())
                        ? ['payment_id' => $p->id, 'amount' => $p->amount, 'method' => $p->method, 'reference' => $p->reference]
                        : ['amount' => $r->amount, 'method' => 'bank'])
                    ->schema([
                        TextInput::make('payment_id')->hidden(),
                        TextInput::make('amount')->numeric()->integer()->prefix('Rs')->required(),
                        Select::make('method')->options(Payment::METHODS)->required(),
                        TextInput::make('reference')->required()->helperText('Bank / JazzCash / Easypaisa transaction ID'),
                    ])
                    ->action(function (Invoice $record, array $data, BillingService $billing) {
                        $payment = ! empty($data['payment_id']) ? Payment::find($data['payment_id']) : null;
                        $payment ??= $record->payments()->create(['amount' => (int) $data['amount'], 'method' => $data['method'], 'reference' => $data['reference']]);
                        $payment->update(['amount' => (int) $data['amount'], 'method' => $data['method'], 'reference' => $data['reference']]);
                        $invoice = $billing->confirm($payment, auth('super_admin')->user());
                        Notification::make()->success()->title($invoice->status === 'paid' ? 'Invoice paid' : 'Payment recorded (partial)')->send();
                    }),
                Action::make('void')->color('gray')->requiresConfirmation()->visible(fn (Invoice $r) => $r->status === 'open')
                    ->action(fn (Invoice $r) => $r->update(['status' => 'void'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvoices::route('/')];
    }
}
