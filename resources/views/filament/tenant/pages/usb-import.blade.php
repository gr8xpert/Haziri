<x-filament-panels::page>
    <form wire:submit="check" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">{{ __('Preview') }}</x-filament::button>
    </form>

    @if ($preview)
        <x-filament::section :heading="__('Preview')">
            <dl class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div><dt class="text-gray-500">{{ __('Rows') }}</dt><dd class="text-lg font-semibold">{{ $preview['total'] }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Already imported') }}</dt><dd class="text-lg font-semibold">{{ $preview['duplicates'] }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Unmapped PINs') }}</dt><dd class="text-lg font-semibold">{{ count($preview['unmapped']) }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Dates') }}</dt><dd class="font-semibold">{{ $preview['from'] }} → {{ $preview['to'] }}</dd></div>
            </dl>

            @if ($preview['unmapped'])
                <h3 class="mt-4 text-sm font-semibold">{{ __('Map PINs to employees (optional)') }}</h3>
                <table class="mt-2 text-sm">
                    @foreach ($preview['unmapped'] as $pin => $count)
                        <tr wire:key="pin-{{ $pin }}">
                            <td class="py-1 pe-4 font-mono">PIN {{ $pin }}</td>
                            <td class="pe-4 text-gray-500">{{ $count }} {{ __('punches') }}</td>
                            <td>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model="mapping.{{ $pin }}">
                                        <option value="">{{ __('— leave unmapped —') }}</option>
                                        @foreach ($this->getEmployeeOptions() as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if ($preview['errors'])
                <details class="mt-4 text-sm"><summary>{{ count($preview['errors']) }} {{ __('lines skipped') }}</summary>
                    <ul class="mt-1 text-gray-500">@foreach ($preview['errors'] as $err)<li>{{ $err }}</li>@endforeach</ul>
                </details>
            @endif

            <x-filament::button class="mt-4" wire:click="import" icon="heroicon-o-arrow-down-tray" :disabled="$preview['total'] === 0">
                {{ __('Import') }}
            </x-filament::button>
        </x-filament::section>
    @endif

    <x-filament::section :heading="__('Recent imports')" collapsible>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500">
                <th class="p-2">{{ __('When') }}</th><th class="p-2">{{ __('Device') }}</th><th class="p-2">{{ __('File') }}</th>
                <th class="p-2">{{ __('Rows') }}</th><th class="p-2">{{ __('Imported') }}</th><th class="p-2">{{ __('Duplicates') }}</th><th class="p-2">{{ __('Unmapped') }}</th>
            </tr></thead>
            <tbody>
                @forelse ($this->getHistory() as $imp)
                    <tr class="border-t border-gray-100 dark:border-white/5">
                        <td class="p-2">{{ $imp->created_at->diffForHumans() }}</td>
                        <td class="p-2">{{ $imp->device?->name ?? '—' }}</td>
                        <td class="p-2">{{ $imp->log['file'] ?? basename($imp->file_path) }}</td>
                        <td class="p-2">{{ $imp->rows_total }}</td>
                        <td class="p-2">{{ $imp->rows_imported }}</td>
                        <td class="p-2">{{ $imp->rows_duplicate }}</td>
                        <td class="p-2">{{ $imp->rows_unmapped }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-center text-gray-500">{{ __('No imports yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
