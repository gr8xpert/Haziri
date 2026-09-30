<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
        <label class="text-sm">
            <span class="block font-medium">{{ __('Date') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="date" max="{{ now(tenant('timezone'))->format('Y-m-d') }}" />
            </x-filament::input.wrapper>
        </label>
        <label class="text-sm">
            <span class="block font-medium">{{ __('Branch') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="branchId">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach ($this->getBranchOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <label class="grow text-sm">
            <span class="block font-medium">{{ __('Reason (saved with every entry)') }}</span>
            <x-filament::input.wrapper>
                <x-filament::input type="text" wire:model="reason" required />
            </x-filament::input.wrapper>
        </label>
        <x-filament::button color="gray" wire:click="fillPresent" icon="heroicon-o-check">
            {{ __('Fill empty rows with shift times') }}
        </x-filament::button>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="p-3">{{ __('Code') }}</th>
                    <th class="p-3">{{ __('Employee') }}</th>
                    <th class="p-3">{{ __('Shift') }}</th>
                    <th class="p-3">{{ __('In') }}</th>
                    <th class="p-3">{{ __('Out') }}</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $id => $row)
                    <tr wire:key="row-{{ $id }}" class="border-t border-gray-100 dark:border-white/5">
                        <td class="p-3 font-mono">{{ $row['code'] }}</td>
                        <td class="p-3">{{ $row['name'] }}</td>
                        <td class="p-3 text-gray-500">{{ $row['shift_start'] }}–{{ $row['shift_end'] }}</td>
                        <td class="p-2">
                            <x-filament::input.wrapper>
                                <x-filament::input type="time" wire:model="rows.{{ $id }}.in" />
                            </x-filament::input.wrapper>
                        </td>
                        <td class="p-2">
                            <x-filament::input.wrapper>
                                <x-filament::input type="time" wire:model="rows.{{ $id }}.out" />
                            </x-filament::input.wrapper>
                        </td>
                        <td class="p-2 whitespace-nowrap">
                            <x-filament::link tag="button" wire:click="markRow({{ $id }}, 'present')">{{ __('Present') }}</x-filament::link>
                            ·
                            <x-filament::link tag="button" color="gray" wire:click="markRow({{ $id }}, 'reset')">{{ __('Reset') }}</x-filament::link>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">{{ __('No active employees for this date.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        <x-filament::button wire:click="save" icon="heroicon-o-check-circle">{{ __('Save changes') }}</x-filament::button>
        <span class="ms-3 text-sm text-gray-500">{{ __('Leave a row empty for absent. Existing punches are never deleted here.') }}</span>
    </div>
</x-filament-panels::page>
