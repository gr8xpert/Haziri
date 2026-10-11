<x-filament-panels::page>
    <form wire:submit="propose" class="hz-card flex flex-col gap-3 p-4 sm:p-5">
        <label for="hz-policy" class="hz-label">{{ __('Your attendance and leave policy') }}</label>
        <textarea id="hz-policy" wire:model="policy" rows="10" class="hz-native"
                  placeholder="{{ __('Paste the policy text here. Grace time, half-day rules, leave quotas, weekly offs...') }}"
                  @error('policy') aria-invalid="true" aria-describedby="hz-policy-error" @enderror></textarea>
        @error('policy')
            <p id="hz-policy-error" class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-m-sparkles" wire:loading.attr="disabled" wire:target="propose">{{ __('Propose settings') }}</x-filament::button>
            <span wire:loading.flex wire:target="propose" class="items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <x-filament::loading-indicator class="size-4" /> {{ __('Reading the policy...') }}
            </span>
        </div>
    </form>

    @if ($error)
        <div class="flex items-start gap-3 rounded-xl bg-danger-50 p-4 text-sm text-danger-700 ring-1 ring-danger-600/15 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/20">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 size-5 shrink-0" />
            <p>{{ $error }}</p>
        </div>
    @endif

    @if ($diff)
        <x-filament::section :heading="__('Proposed changes')" :description="__('Tick the ones to apply. Nothing changes until you press Apply.')">
            <div class="-mx-6 -mt-6 overflow-x-auto">
                <table class="hz-table">
                    <thead>
                        <tr>
                            <th class="w-10"><span class="sr-only">{{ __('Apply') }}</span></th>
                            <th>{{ __('Setting') }}</th>
                            <th>{{ __('Now') }}</th>
                            <th>{{ __('Proposed') }}</th>
                            <th>{{ __('From the policy') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($diff as $i => $c)
                            <tr>
                                <td><x-filament::input.checkbox wire:model="accept.{{ $i }}" aria-label="{{ __('Apply :s', ['s' => $c['key']]) }}" /></td>
                                <td class="font-mono text-xs whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $c['target'] }}.{{ $c['key'] }}</td>
                                <td class="text-gray-500 line-through decoration-gray-300 dark:text-gray-400 dark:decoration-gray-600">{{ is_array($c['current']) ? implode(', ', $c['current']) : var_export($c['current'], true) }}</td>
                                <td class="font-semibold text-gray-950 dark:text-white">{{ is_array($c['proposed']) ? implode(', ', $c['proposed']) : var_export($c['proposed'], true) }}</td>
                                <td class="max-w-md text-gray-500 dark:text-gray-400">{{ $c['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-filament::button class="mt-5" wire:click="apply" icon="heroicon-m-check">{{ __('Apply selected') }}</x-filament::button>
        </x-filament::section>
    @endif
</x-filament-panels::page>
