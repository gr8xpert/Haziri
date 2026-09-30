<x-filament-panels::page>
    <form wire:submit="ask" class="flex gap-2">
        <x-filament::input.wrapper class="grow">
            <x-filament::input type="text" wire:model="question" placeholder="{{ __('e.g. Who in Sales was late more than 3 times in August?') }}" />
        </x-filament::input.wrapper>
        <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled">{{ __('Ask') }}</x-filament::button>
    </form>
    <p class="text-xs text-gray-500">{{ __('Answers are read-only queries over attendance, leave and employee data. AI budget left this month: :n tokens.', ['n' => number_format($this->tokensLeft())]) }}</p>

    <div wire:loading wire:target="ask" class="text-sm text-gray-500">{{ __('Thinking…') }}</div>

    @if ($answer)
        @if (! $answer['ok'])
            <x-filament::section><p class="text-sm text-danger-600">{{ $answer['error'] }}</p></x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">{{ count($answer['rows']) }} {{ __('rows') }}</x-slot>
                <x-slot name="headerEnd">
                    <x-filament::button size="xs" color="gray" wire:click="export" icon="heroicon-o-table-cells">Excel</x-filament::button>
                    <x-filament::icon-button icon="heroicon-o-hand-thumb-up" wire:click="feedback(1)" label="Good answer" />
                    <x-filament::icon-button icon="heroicon-o-hand-thumb-down" wire:click="feedback(-1)" label="Bad answer" />
                </x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead><tr class="text-left text-gray-500">@foreach ($answer['columns'] as $c)<th class="p-2">{{ $c }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach (array_slice($answer['rows'], 0, 500) as $row)
                                <tr class="border-t border-gray-100 dark:border-white/5">@foreach ($row as $v)<td class="p-2">{{ $v }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <details class="mt-3 text-xs text-gray-500"><summary>SQL</summary><pre class="mt-1 whitespace-pre-wrap">{{ $answer['sql'] }}</pre></details>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
