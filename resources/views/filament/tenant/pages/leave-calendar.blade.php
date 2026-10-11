<x-filament-panels::page>
    @php
        $start = \Carbon\Carbon::parse($weekStart);
        $today = now(tenant('timezone'))->format('Y-m-d');
    @endphp

    <div class="hz-toolbar items-center">
        <div class="flex items-center gap-1">
            <x-filament::icon-button icon="heroicon-m-chevron-left" color="gray" wire:click="shift(-1)" :label="__('Previous week')" />
            <span class="min-w-44 text-center text-sm font-semibold text-gray-950 tabular-nums dark:text-white">
                {{ $start->format('d M') }} - {{ $start->copy()->addDays(6)->format('d M Y') }}
            </span>
            <x-filament::icon-button icon="heroicon-m-chevron-right" color="gray" wire:click="shift(1)" :label="__('Next week')" />
        </div>
        @if (count($this->getBranchOptions()) > 1)
            <x-filament::input.wrapper class="w-full sm:w-52">
                <x-filament::input.select wire:model.live="branchId" aria-label="{{ __('Branch') }}">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach ($this->getBranchOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        @endif
        <x-filament::input.wrapper class="w-full sm:w-52">
            <x-filament::input.select wire:model.live="departmentId" aria-label="{{ __('Department') }}">
                <option value="">{{ __('All departments') }}</option>
                @foreach ($this->getDepartmentOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        @unless ($this->seesWholeCompany())
            <span class="hz-hint">{{ __('Showing your team.') }}</span>
        @endunless
        <div class="flex flex-wrap items-center gap-2 text-xs sm:ms-auto">
            <x-filament::badge color="warning" size="sm">{{ __('Leave') }}</x-filament::badge>
            <x-filament::badge color="info" size="sm">{{ __('Official duty / work from home') }}</x-filament::badge>
            <x-filament::badge color="success" size="sm">{{ __('Holiday') }}</x-filament::badge>
            <x-filament::badge color="gray" size="sm">{{ __('Off day') }}</x-filament::badge>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7" wire:loading.class="opacity-60">
        @foreach ($this->getDays() as $date => $entries)
            @php($day = \Carbon\Carbon::parse($date))
            <div @class([
                'hz-card flex min-h-36 flex-col p-3',
                'ring-2! ring-primary-500/60!' => $date === $today,
            ])>
                <div class="mb-2 flex items-baseline justify-between">
                    <span class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">{{ $day->format('D') }}</span>
                    <span @class([
                        'text-lg font-semibold tabular-nums',
                        'text-primary-600 dark:text-primary-400' => $date === $today,
                        'text-gray-950 dark:text-white' => $date !== $today,
                    ])>{{ $day->format('d') }}</span>
                </div>
                <div class="flex flex-col gap-1.5">
                    @forelse ($entries as $e)
                        <div class="flex items-center justify-between gap-2 rounded-md bg-gray-50 px-2 py-1 text-sm dark:bg-white/5">
                            <span class="truncate text-gray-800 dark:text-gray-200">{{ $e['name'] }}</span>
                            <x-filament::badge :color="$e['tone']" size="sm">{{ $e['what'] }}</x-filament::badge>
                        </div>
                    @empty
                        <span class="mt-auto inline-flex items-center gap-1.5 text-sm text-gray-400 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-m-check-circle" class="size-4" />
                            {{ __('Everyone in') }}
                        </span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
