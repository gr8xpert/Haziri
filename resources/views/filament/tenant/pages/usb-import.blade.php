<x-filament-panels::page>
    <form wire:submit="check" class="hz-card flex flex-col gap-4 p-4 sm:p-5">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit" icon="heroicon-m-magnifying-glass" wire:loading.attr="disabled" wire:target="check">{{ __('Preview') }}</x-filament::button>
        </div>
    </form>

    @if ($preview)
        <x-filament::section :heading="__('Preview')" icon="heroicon-o-eye">
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                <div class="hz-kpi"><dt>{{ __('Rows') }}</dt><dd>{{ number_format($preview['total']) }}</dd></div>
                <div class="hz-kpi"><dt>{{ __('Already imported') }}</dt><dd>{{ number_format($preview['duplicates']) }}</dd></div>
                <div class="hz-kpi"><dt>{{ __('Unmapped PINs') }}</dt><dd @class(['text-warning-600! dark:text-warning-400!' => count($preview['unmapped'])])>{{ count($preview['unmapped']) }}</dd></div>
                <div class="hz-kpi"><dt>{{ __('Dates') }}</dt><dd class="text-base!">{{ $preview['from'] }} <span class="text-gray-400">to</span> {{ $preview['to'] }}</dd></div>
            </dl>

            @if ($preview['unmapped'])
                <div class="mt-6 border-t border-gray-950/[0.06] pt-5 dark:border-white/[0.06]">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Map PINs to employees') }}</h3>
                    <p class="hz-hint mt-0.5">{{ __('Optional. Unmapped punches are kept and can be mapped later.') }}</p>
                    <div class="mt-3 flex flex-col divide-y divide-gray-950/[0.05] dark:divide-white/[0.05]">
                        @foreach ($preview['unmapped'] as $pin => $count)
                            <div class="grid items-center gap-2 py-2 sm:grid-cols-[8rem_8rem_1fr]" wire:key="pin-{{ $pin }}">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">PIN {{ $pin }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ trans_choice(':n punch|:n punches', $count, ['n' => $count]) }}</span>
                                <x-filament::input.wrapper class="max-w-md">
                                    <x-filament::input.select wire:model="mapping.{{ $pin }}" aria-label="{{ __('Employee for PIN :p', ['p' => $pin]) }}">
                                        <option value="">{{ __('Leave unmapped') }}</option>
                                        @foreach ($this->getEmployeeOptions() as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($preview['errors'])
                <details class="group mt-5 rounded-lg bg-warning-50 p-3 text-sm ring-1 ring-warning-600/15 dark:bg-warning-500/10 dark:ring-warning-400/20">
                    <summary class="flex cursor-pointer items-center gap-2 font-medium text-warning-800 dark:text-warning-300">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="size-4 transition-transform group-open:rotate-90" />
                        {{ trans_choice(':n line skipped|:n lines skipped', count($preview['errors']), ['n' => count($preview['errors'])]) }}
                    </summary>
                    <ul class="mt-2 space-y-0.5 ps-6 font-mono text-xs text-warning-900/80 dark:text-warning-200/80">@foreach ($preview['errors'] as $err)<li>{{ $err }}</li>@endforeach</ul>
                </details>
            @endif

            <x-filament::button class="mt-6" wire:click="import" icon="heroicon-m-arrow-down-tray" :disabled="$preview['total'] === 0">
                {{ __('Import') }}
            </x-filament::button>
        </x-filament::section>
    @endif

    <x-filament::section :heading="__('Recent imports')" collapsible>
        <div class="-m-6 overflow-x-auto">
            <table class="hz-table">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th><th>{{ __('Device') }}</th><th>{{ __('File') }}</th>
                        <th class="text-end">{{ __('Rows') }}</th><th class="text-end">{{ __('Imported') }}</th>
                        <th class="text-end">{{ __('Duplicates') }}</th><th class="text-end">{{ __('Unmapped') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getHistory() as $imp)
                        <tr>
                            <td class="whitespace-nowrap" title="{{ $imp->created_at }}">{{ $imp->created_at->diffForHumans() }}</td>
                            <td>{{ $imp->device?->name ?? '-' }}</td>
                            <td class="max-w-64 truncate">{{ $imp->log['file'] ?? basename($imp->file_path) }}</td>
                            <td class="text-end">{{ $imp->rows_total }}</td>
                            <td class="text-end font-medium text-gray-950 dark:text-white">{{ $imp->rows_imported }}</td>
                            <td class="text-end">{{ $imp->rows_duplicate }}</td>
                            <td @class(['text-end', 'text-warning-600 dark:text-warning-400' => $imp->rows_unmapped])>{{ $imp->rows_unmapped }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="hz-empty">
                                    <x-filament::icon icon="heroicon-o-arrow-up-tray" class="hz-empty-icon" />
                                    <strong>{{ __('No imports yet') }}</strong>
                                    <span>{{ __('Upload a .dat file exported from the machine to get started.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
