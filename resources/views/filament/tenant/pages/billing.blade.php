<x-filament-panels::page>
    <x-filament::section :heading="__('How to pay')" icon="heroicon-o-banknotes">
        <p class="max-w-3xl text-sm leading-relaxed whitespace-pre-line text-gray-700 dark:text-gray-300">{{ config('hazri.billing.payment_instructions') }}</p>
    </x-filament::section>

    <div class="hz-table-wrap">
        <table class="hz-table">
            <thead>
                <tr>
                    <th>{{ __('Invoice') }}</th>
                    <th>{{ __('Month') }}</th>
                    <th class="text-end">{{ __('Employees') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th>{{ __('Due') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th><span class="sr-only">{{ __('Payment') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getInvoices() as $inv)
                    @php($awaiting = $inv->payments->where('status', 'submitted')->isNotEmpty())
                    <tr wire:key="inv-{{ $inv->id }}">
                        <td class="font-medium text-gray-950 dark:text-white">{{ $inv->number }}</td>
                        <td class="whitespace-nowrap">{{ $inv->period_start->format('M Y') }}</td>
                        <td class="text-end">{{ $inv->employee_count }}</td>
                        <td class="text-end font-medium whitespace-nowrap text-gray-950 dark:text-white">Rs {{ number_format($inv->amount) }}</td>
                        <td class="whitespace-nowrap">{{ $inv->due_on->format('d M Y') }}</td>
                        <td>
                            @if ($awaiting)
                                <x-filament::badge color="info" icon="heroicon-m-clock">{{ __('Awaiting confirmation') }}</x-filament::badge>
                            @else
                                <x-filament::badge :color="$inv->status === 'paid' ? 'success' : 'warning'">{{ ucfirst($inv->status) }}</x-filament::badge>
                            @endif
                        </td>
                        <td class="py-1.5">
                            @if ($inv->status === 'open' && ! $awaiting)
                                <div class="flex min-w-[28rem] items-center gap-2">
                                    <select wire:model="pay.{{ $inv->id }}.method" class="hz-native w-36" aria-label="{{ __('Payment method') }}">
                                        <option value="">{{ __('Method') }}</option>
                                        @foreach (\App\Models\Central\Payment::METHODS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                    </select>
                                    <x-filament::input.wrapper class="flex-1">
                                        <x-filament::input wire:model="pay.{{ $inv->id }}.reference" :placeholder="__('Transaction ID')" aria-label="{{ __('Transaction ID') }}" />
                                    </x-filament::input.wrapper>
                                    <x-filament::button size="sm" wire:click="submit({{ $inv->id }})">{{ __('I have paid') }}</x-filament::button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="hz-empty">
                                <x-filament::icon icon="heroicon-o-document-text" class="hz-empty-icon" />
                                <strong>{{ __('No invoices yet') }}</strong>
                                <span>{{ __('Your first invoice appears at the start of next month.') }}</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
