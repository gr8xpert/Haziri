<x-filament-panels::page>
    <div class="hz-card p-4 sm:p-5">
        <form wire:submit="ask" class="flex flex-col gap-3 sm:flex-row">
            <label class="sr-only" for="hz-ask">{{ __('Your question') }}</label>
            <x-filament::input.wrapper class="grow" prefix-icon="heroicon-m-sparkles">
                <x-filament::input id="hz-ask" type="text" wire:model="question" placeholder="{{ __('e.g. Who in Sales was late more than 3 times in August?') }}" />
            </x-filament::input.wrapper>
            <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled" wire:target="ask">{{ __('Ask') }}</x-filament::button>
        </form>
        <p class="hz-hint mt-3">
            {{ __('Answers are read-only queries over attendance, leave and employee data.') }}
            <span class="whitespace-nowrap">{{ __('AI budget left this month: :n tokens.', ['n' => number_format($this->tokensLeft())]) }}</span>
        </p>
    </div>

    <div wire:loading.flex wire:target="ask" class="hz-card items-center gap-3 p-5 text-sm text-gray-500 dark:text-gray-400">
        <x-filament::loading-indicator class="size-5 text-primary-600" />
        {{ __('Reading your data...') }}
    </div>

    @if ($answer)
        @if (! $answer['ok'])
            <div class="flex items-start gap-3 rounded-xl bg-danger-50 p-4 text-sm text-danger-700 ring-1 ring-danger-600/15 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/20" wire:loading.remove wire:target="ask">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
                <p>{{ $answer['error'] }}</p>
            </div>
        @else
            <x-filament::section wire:loading.remove wire:target="ask">
                <x-slot name="heading">{{ trans_choice(':n row|:n rows', count($answer['rows']), ['n' => count($answer['rows'])]) }}</x-slot>
                <x-slot name="afterHeader">
                    <div class="flex items-center gap-1">
                        @can('reports.export')
                            <x-filament::button size="sm" color="gray" wire:click="export" icon="heroicon-m-arrow-down-tray">Excel</x-filament::button>
                        @endcan
                        <x-filament::icon-button icon="heroicon-o-hand-thumb-up" color="gray" wire:click="feedback(1)" :label="__('Good answer')" />
                        <x-filament::icon-button icon="heroicon-o-hand-thumb-down" color="gray" wire:click="feedback(-1)" :label="__('Bad answer')" />
                    </div>
                </x-slot>
                <div class="-mx-6 -my-6 overflow-x-auto">
                    <table class="hz-table">
                        <thead><tr>@foreach ($answer['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach (array_slice($answer['rows'], 0, 500) as $row)
                                <tr>@foreach ($row as $v)<td class="whitespace-nowrap">{{ $v }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <details class="group mt-10 text-xs">
                    <summary class="inline-flex cursor-pointer items-center gap-1 font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="size-4 transition-transform group-open:rotate-90" />
                        {{ __('Show SQL') }}
                    </summary>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-50 p-3 font-mono whitespace-pre-wrap text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">{{ $answer['sql'] }}</pre>
                </details>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
