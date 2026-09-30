<x-filament-panels::page>
    <x-filament::section :heading="__('How to pay')">
        <p class="text-sm">{{ config('hazri.billing.payment_instructions') }}</p>
    </x-filament::section>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500">
                <th class="p-3">{{ __('Invoice') }}</th><th class="p-3">{{ __('Month') }}</th><th class="p-3">{{ __('Employees') }}</th>
                <th class="p-3">{{ __('Amount') }}</th><th class="p-3">{{ __('Due') }}</th><th class="p-3">{{ __('Status') }}</th><th class="p-3"></th>
            </tr></thead>
            <tbody>
                @forelse ($this->getInvoices() as $inv)
                    <tr class="border-t border-gray-100 dark:border-white/5" wire:key="inv-{{ $inv->id }}">
                        <td class="p-3 font-mono">{{ $inv->number }}</td>
                        <td class="p-3">{{ $inv->period_start->format('M Y') }}</td>
                        <td class="p-3">{{ $inv->employee_count }}</td>
                        <td class="p-3">Rs {{ number_format($inv->amount) }}</td>
                        <td class="p-3">{{ $inv->due_on->format('d M Y') }}</td>
                        <td class="p-3">
                            <x-filament::badge :color="$inv->status === 'paid' ? 'success' : 'warning'">{{ $inv->status }}</x-filament::badge>
                            @if ($inv->payments->where('status', 'submitted')->isNotEmpty())
                                <span class="text-xs text-gray-500">{{ __('payment awaiting confirmation') }}</span>
                            @endif
                        </td>
                        <td class="p-2">
                            @if ($inv->status === 'open' && $inv->payments->where('status', 'submitted')->isEmpty())
                                <div class="flex gap-2">
                                    <select wire:model="pay.{{ $inv->id }}.method" class="rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-gray-800">
                                        <option value="">{{ __('Method') }}</option>
                                        @foreach (\App\Models\Central\Payment::METHODS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                    </select>
                                    <input wire:model="pay.{{ $inv->id }}.reference" placeholder="{{ __('Transaction ID') }}" class="rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-gray-800">
                                    <x-filament::button size="xs" wire:click="submit({{ $inv->id }})">{{ __('I have paid') }}</x-filament::button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-gray-500">{{ __('No invoices yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
