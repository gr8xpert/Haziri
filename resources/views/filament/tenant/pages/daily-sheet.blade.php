<x-filament-panels::page>
    <div class="hz-toolbar">
        <label class="hz-field w-full sm:w-44">
            <span class="hz-label">{{ __('Date') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="date" max="{{ now(tenant('timezone'))->format('Y-m-d') }}" />
            </x-filament::input.wrapper>
        </label>
        <label class="hz-field w-full sm:w-52">
            <span class="hz-label">{{ __('Branch') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="branchId">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach ($this->getBranchOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <label class="hz-field w-full sm:min-w-64 sm:flex-1">
            <span class="hz-label">{{ __('Reason') }} <span class="font-normal text-gray-400">{{ __('(saved with every entry)') }}</span></span>
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="reason" required />
            </x-filament::input.wrapper>
        </label>
        <x-filament::button color="gray" wire:click="fillPresent" icon="heroicon-m-bolt">
            {{ __('Fill empty rows with shift times') }}
        </x-filament::button>
    </div>

    <div class="hz-table-wrap" wire:loading.class="opacity-60" wire:target="date,branchId">
        <table class="hz-table">
            <thead>
                <tr>
                    <th class="w-20">{{ __('Code') }}</th>
                    <th>{{ __('Employee') }}</th>
                    <th>{{ __('Shift') }}</th>
                    <th class="w-36">{{ __('In') }}</th>
                    <th class="w-36">{{ __('Out') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $id => $row)
                    <tr wire:key="row-{{ $id }}">
                        <td class="text-gray-500 dark:text-gray-400">{{ $row['code'] }}</td>
                        <td class="font-medium whitespace-nowrap text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                        <td class="whitespace-nowrap text-gray-500 dark:text-gray-400">
                            @if ($row['shift_start'])
                                {{ $row['shift_start'] }}-{{ $row['shift_end'] }}
                            @else
                                <span class="text-gray-400">{{ __('No shift') }}</span>
                            @endif
                        </td>
                        <td class="py-1.5">
                            <x-filament::input.wrapper>
                                <x-filament::input type="time" wire:model="rows.{{ $id }}.in" aria-label="{{ __('In time for :n', ['n' => $row['name']]) }}" />
                            </x-filament::input.wrapper>
                        </td>
                        <td class="py-1.5">
                            <x-filament::input.wrapper>
                                <x-filament::input type="time" wire:model="rows.{{ $id }}.out" aria-label="{{ __('Out time for :n', ['n' => $row['name']]) }}" />
                            </x-filament::input.wrapper>
                        </td>
                        <td>
                            @if (! $row['in'] && ! $row['out'])
                                <x-filament::badge color="gray">{{ __('Absent') }}</x-filament::badge>
                            @elseif ($row['in'] && ! $row['out'])
                                <x-filament::badge color="warning">{{ __('No out') }}</x-filament::badge>
                            @else
                                <x-filament::badge color="success">{{ __('Present') }}</x-filament::badge>
                            @endif
                        </td>
                        <td class="text-end whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                <x-filament::button size="xs" color="gray" outlined wire:click="markRow({{ $id }}, 'present')">{{ __('Present') }}</x-filament::button>
                                <x-filament::icon-button icon="heroicon-m-arrow-uturn-left" color="gray" size="sm" wire:click="markRow({{ $id }}, 'reset')" :label="__('Reset to recorded punches')" :tooltip="__('Reset')" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="hz-empty">
                                <x-filament::icon icon="heroicon-o-users" class="hz-empty-icon" />
                                <strong>{{ __('No active employees for this date') }}</strong>
                                <span>{{ __('Try another date or branch.') }}</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-gray-950/[0.06] bg-gray-50/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:-mx-8 lg:px-8 dark:border-white/[0.06] dark:bg-gray-950/90">
        <x-filament::button wire:click="save" icon="heroicon-m-check" wire:loading.attr="disabled" wire:target="save">{{ __('Save changes') }}</x-filament::button>
        <span class="hz-hint">{{ __('Leave a row empty for absent. Existing punches are never deleted here.') }}</span>
    </div>
</x-filament-panels::page>
