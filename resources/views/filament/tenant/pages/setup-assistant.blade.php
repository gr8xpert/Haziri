<x-filament-panels::page>
    <form wire:submit="propose" class="space-y-3">
        <textarea wire:model="policy" rows="10" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900"
                  placeholder="{{ __('Paste your attendance / leave policy here…') }}"></textarea>
        <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled">{{ __('Propose settings') }}</x-filament::button>
        <span wire:loading wire:target="propose" class="text-sm text-gray-500">{{ __('Reading the policy…') }}</span>
    </form>

    @if ($error)
        <p class="text-sm text-danger-600">{{ $error }}</p>
    @endif

    @if ($diff)
        <x-filament::section :heading="__('Proposed changes — tick the ones to apply')">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="p-2"></th><th class="p-2">{{ __('Setting') }}</th><th class="p-2">{{ __('Now') }}</th><th class="p-2">{{ __('Proposed') }}</th><th class="p-2">{{ __('From the policy') }}</th></tr></thead>
                <tbody>
                    @foreach ($diff as $i => $c)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="p-2"><input type="checkbox" wire:model="accept.{{ $i }}"></td>
                            <td class="p-2 font-mono">{{ $c['target'] }}.{{ $c['key'] }}</td>
                            <td class="p-2">{{ is_array($c['current']) ? implode(',', $c['current']) : var_export($c['current'], true) }}</td>
                            <td class="p-2 font-semibold">{{ is_array($c['proposed']) ? implode(',', $c['proposed']) : var_export($c['proposed'], true) }}</td>
                            <td class="p-2 text-gray-500">{{ $c['reason'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <x-filament::button class="mt-4" wire:click="apply" icon="heroicon-o-check-circle">{{ __('Apply selected') }}</x-filament::button>
        </x-filament::section>
    @endif
</x-filament-panels::page>
