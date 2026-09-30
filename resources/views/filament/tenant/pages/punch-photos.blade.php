<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        <x-filament::input.wrapper class="w-64">
            <x-filament::input type="text" wire:model.live.debounce.500ms="who" placeholder="{{ __('PIN, code or name') }}" />
        </x-filament::input.wrapper>
        <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
        <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
        @forelse ($this->getPhotos() as $p)
            <figure class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <img src="{{ $p['url'] }}" alt="{{ $p['name'] }}" loading="lazy" class="aspect-[3/4] w-full object-cover">
                <figcaption class="p-2 text-xs">
                    <div class="font-medium">{{ $p['name'] }}</div>
                    <div class="text-gray-500">{{ $p['time'] }} · {{ $p['source'] }}</div>
                    @if ($p['flag'])<x-filament::badge color="warning" size="sm">{{ __('outside geofence') }}</x-filament::badge>@endif
                </figcaption>
            </figure>
        @empty
            <p class="col-span-full text-sm text-gray-500">{{ __('No punch photos for these filters.') }}</p>
        @endforelse
    </div>
</x-filament-panels::page>
