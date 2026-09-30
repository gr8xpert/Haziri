<x-filament-panels::page>
    <div class="flex items-center gap-2">
        <x-filament::button color="gray" size="sm" wire:click="shift(-1)" icon="heroicon-o-chevron-left">{{ __('Previous') }}</x-filament::button>
        <span class="text-sm font-medium">{{ \Carbon\Carbon::parse($weekStart)->format('d M') }} – {{ \Carbon\Carbon::parse($weekStart)->addDays(6)->format('d M Y') }}</span>
        <x-filament::button color="gray" size="sm" wire:click="shift(1)" icon="heroicon-o-chevron-right" icon-position="after">{{ __('Next') }}</x-filament::button>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
        @foreach ($this->getDays() as $date => $entries)
            <x-filament::section :compact="true">
                <x-slot name="heading">{{ \Carbon\Carbon::parse($date)->format('D d') }}</x-slot>
                @forelse ($entries as $e)
                    <div class="flex items-center justify-between gap-2 py-0.5 text-sm">
                        <span class="truncate">{{ $e['name'] }}</span>
                        <x-filament::badge :color="$e['tone']" size="sm">{{ $e['what'] }}</x-filament::badge>
                    </div>
                @empty
                    <span class="text-sm text-gray-400">{{ __('Everyone in') }}</span>
                @endforelse
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
