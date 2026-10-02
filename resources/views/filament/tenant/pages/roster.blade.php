<x-filament-panels::page>
    @php
        $start = \Carbon\Carbon::parse($weekStart);
        $shifts = $this->getShiftOptions();
        $dates = $this->getDates();
        $today = now(tenant('timezone'))->format('Y-m-d');
    @endphp

    <div class="hz-toolbar items-center">
        <div class="flex items-center gap-1">
            <x-filament::icon-button icon="heroicon-m-chevron-left" color="gray" wire:click="shiftWeek(-1)" :label="__('Previous week')" />
            <span class="min-w-44 text-center text-sm font-semibold text-gray-950 tabular-nums dark:text-white">
                {{ $start->format('d M') }} - {{ $start->copy()->addDays(6)->format('d M Y') }}
            </span>
            <x-filament::icon-button icon="heroicon-m-chevron-right" color="gray" wire:click="shiftWeek(1)" :label="__('Next week')" />
        </div>
        <x-filament::input.wrapper class="w-full sm:w-52">
            <x-filament::input.select wire:model.live="branchId" aria-label="{{ __('Branch') }}">
                <option value="">{{ __('All branches') }}</option>
                @foreach ($this->getBranchOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <div class="flex flex-wrap gap-2 sm:ms-auto">
            <x-filament::button color="gray" wire:click="copyPreviousWeek" icon="heroicon-m-document-duplicate">{{ __('Copy last week') }}</x-filament::button>
            <x-filament::button wire:click="save" icon="heroicon-m-check">{{ __('Publish') }}</x-filament::button>
        </div>
    </div>

    <div class="hz-table-wrap" wire:loading.class="opacity-60">
        <table class="hz-table hz-table-dense">
            <thead>
                <tr>
                    <th>{{ __('Employee') }}</th>
                    @foreach ($dates as $d)
                        @php($day = \Carbon\Carbon::parse($d))
                        <th @class(['min-w-28 text-center', 'text-primary-600 dark:text-primary-400' => $d === $today])>
                            <span class="block">{{ $day->format('D') }}</span>
                            <span class="block text-sm font-semibold text-gray-950 tabular-nums dark:text-white">{{ $day->format('d') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($this->getEmployees() as $e)
                    <tr wire:key="r-{{ $e->id }}">
                        <td>
                            <span class="block font-medium text-gray-950 dark:text-white">{{ $e->fullName() }}</span>
                            <span class="block text-gray-500 dark:text-gray-400">{{ $e->code }}</span>
                        </td>
                        @foreach ($dates as $d)
                            <td class="px-1.5">
                                <select wire:model="grid.{{ $e->id }}.{{ $d }}" class="hz-native text-xs" aria-label="{{ __('Shift for :n on :d', ['n' => $e->fullName(), 'd' => $d]) }}">
                                    <option value="">{{ __('Regular') }}</option>
                                    @foreach ($shifts as $id => $code)<option value="{{ $id }}">{{ $code }}</option>@endforeach
                                </select>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($dates) + 1 }}">
                            <div class="hz-empty">
                                <x-filament::icon icon="heroicon-o-calendar" class="hz-empty-icon" />
                                <strong>{{ __('No employees in this branch') }}</strong>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="hz-hint">{{ __('"Regular" keeps the employee\'s usual shift. Publishing recalculates the affected days.') }}</p>
</x-filament-panels::page>
