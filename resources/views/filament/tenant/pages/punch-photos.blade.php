<x-filament-panels::page>
    <div class="hz-toolbar">
        <label class="hz-field w-full sm:w-72">
            <span class="hz-label">{{ __('Employee') }}</span>
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" wire:model.live.debounce.500ms="who" placeholder="{{ __('PIN, code or name') }}" />
            </x-filament::input.wrapper>
        </label>
        <label class="hz-field w-full sm:w-44">
            <span class="hz-label">{{ __('From') }}</span>
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
        </label>
        <label class="hz-field w-full sm:w-44">
            <span class="hz-label">{{ __('To') }}</span>
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper>
        </label>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6" wire:loading.class="opacity-60">
        @forelse ($this->getPhotos() as $p)
            <figure class="hz-card group overflow-hidden">
                <a href="{{ $p['url'] }}" target="_blank" rel="noopener" class="relative block overflow-hidden bg-gray-100 dark:bg-white/5">
                    <img src="{{ $p['url'] }}" alt="{{ __('Punch photo of :n', ['n' => $p['name']]) }}" loading="lazy" decoding="async"
                         class="aspect-[3/4] w-full object-cover transition-transform duration-300 ease-out motion-safe:group-hover:scale-[1.03]">
                    @if ($p['flag'])
                        <span class="absolute inset-x-2 bottom-2">
                            <x-filament::badge color="warning" size="sm" icon="heroicon-m-map-pin">{{ __('Outside geofence') }}</x-filament::badge>
                        </span>
                    @endif
                </a>
                <figcaption class="px-3 py-2.5">
                    <div class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $p['name'] }}</div>
                    <div class="truncate text-xs text-gray-500 tabular-nums dark:text-gray-400">{{ $p['time'] }} · {{ $p['source'] }}</div>
                </figcaption>
            </figure>
        @empty
            <div class="hz-card col-span-full">
                <div class="hz-empty">
                    <x-filament::icon icon="heroicon-o-camera" class="hz-empty-icon" />
                    <strong>{{ __('No punch photos for these filters') }}</strong>
                    <span>{{ __('Photos are taken at the kiosk and in the mobile app.') }}</span>
                </div>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
