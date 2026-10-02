<x-filament-panels::page>
    <form wire:submit="check" class="hz-card flex flex-col gap-4 p-4 sm:p-5">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit" icon="heroicon-m-magnifying-glass" wire:loading.attr="disabled" wire:target="check">
                {{ __('Check file') }}
            </x-filament::button>
        </div>
    </form>

    @if ($preview)
        <x-filament::section :heading="__('Preview')" icon="heroicon-o-eye">
            <dl class="grid grid-cols-3 gap-6 sm:max-w-lg">
                <div class="hz-kpi"><dt>{{ __('New') }}</dt><dd>{{ $preview['creates'] }}</dd></div>
                <div class="hz-kpi"><dt>{{ __('Updates') }}</dt><dd>{{ $preview['updates'] }}</dd></div>
                <div class="hz-kpi"><dt>{{ __('Errors') }}</dt><dd @class(['text-danger-600! dark:text-danger-400!' => count($preview['errors'])])>{{ count($preview['errors']) }}</dd></div>
            </dl>

            @if ($preview['errors'])
                <div class="mt-5 rounded-lg bg-danger-50 p-4 ring-1 ring-danger-600/15 dark:bg-danger-500/10 dark:ring-danger-400/20">
                    <ul class="space-y-1 text-sm text-danger-700 dark:text-danger-400">
                        @foreach ($preview['errors'] as $line => $messages)
                            <li><strong class="font-semibold">{{ __('Row :n', ['n' => $line]) }}:</strong> {{ implode(' ', $messages) }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-sm text-danger-700/80 dark:text-danger-300/80">{{ __('Rows with errors are skipped. Fix them and upload again, or import the valid rows now.') }}</p>
                </div>
            @endif

            @if ($preview['rows'])
                <div class="hz-table-wrap mt-5 shadow-none!">
                    <table class="hz-table">
                        <thead>
                            <tr>
                                <th>{{ __('Code') }}</th><th>{{ __('Name') }}</th>
                                <th>{{ __('PIN') }}</th><th>{{ __('Joined') }}</th><th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($preview['rows'], 0, 50) as $row)
                                <tr>
                                    <td class="text-gray-500 dark:text-gray-400">{{ $row['code'] }}</td>
                                    <td class="font-medium text-gray-950 dark:text-white">{{ trim($row['first_name'] . ' ' . $row['last_name']) }}</td>
                                    <td>{{ $row['device_pin'] }}</td>
                                    <td class="whitespace-nowrap">{{ $row['joined_on'] }}</td>
                                    <td>
                                        <x-filament::badge :color="$row['_exists'] ? 'info' : 'success'">{{ $row['_exists'] ? __('Update') : __('New') }}</x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (count($preview['rows']) > 50)
                    <p class="hz-hint mt-2">{{ __('Showing the first 50 of :n rows.', ['n' => count($preview['rows'])]) }}</p>
                @endif
                <x-filament::button class="mt-5" wire:click="import" icon="heroicon-m-arrow-down-tray">
                    {{ __('Import :n rows', ['n' => count($preview['rows'])]) }}
                </x-filament::button>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
