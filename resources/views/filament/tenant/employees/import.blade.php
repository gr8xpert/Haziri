<x-filament-panels::page>
    <form wire:submit="check" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
            {{ __('Check file') }}
        </x-filament::button>
    </form>

    @if ($preview)
        <x-filament::section :heading="__('Preview')">
            <p class="text-sm">
                {{ __(':c new, :u updates, :e rows with errors', ['c' => $preview['creates'], 'u' => $preview['updates'], 'e' => count($preview['errors'])]) }}
            </p>

            @if ($preview['errors'])
                <ul class="mt-3 space-y-1 text-sm text-danger-600 dark:text-danger-400">
                    @foreach ($preview['errors'] as $line => $messages)
                        <li><strong>{{ __('Row :n', ['n' => $line]) }}:</strong> {{ implode(' ', $messages) }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-sm text-gray-500">{{ __('Rows with errors are skipped. Fix them and upload again, or import the valid rows now.') }}</p>
            @endif

            @if ($preview['rows'])
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left">
                                <th class="p-2">{{ __('Code') }}</th><th class="p-2">{{ __('Name') }}</th>
                                <th class="p-2">{{ __('PIN') }}</th><th class="p-2">{{ __('Joined') }}</th><th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($preview['rows'], 0, 50) as $row)
                                <tr class="border-t border-gray-200 dark:border-white/10">
                                    <td class="p-2">{{ $row['code'] }}</td>
                                    <td class="p-2">{{ trim($row['first_name'] . ' ' . $row['last_name']) }}</td>
                                    <td class="p-2">{{ $row['device_pin'] }}</td>
                                    <td class="p-2">{{ $row['joined_on'] }}</td>
                                    <td class="p-2">{{ $row['_exists'] ? __('update') : __('new') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-filament::button class="mt-4" wire:click="import" icon="heroicon-o-arrow-down-tray">
                    {{ __('Import :n rows', ['n' => count($preview['rows'])]) }}
                </x-filament::button>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
