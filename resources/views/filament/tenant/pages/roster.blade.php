<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button color="gray" size="sm" wire:click="shiftWeek(-1)" icon="heroicon-o-chevron-left">{{ __('Previous') }}</x-filament::button>
        <span class="text-sm font-medium">{{ \Carbon\Carbon::parse($weekStart)->format('d M') }} – {{ \Carbon\Carbon::parse($weekStart)->addDays(6)->format('d M Y') }}</span>
        <x-filament::button color="gray" size="sm" wire:click="shiftWeek(1)" icon="heroicon-o-chevron-right" icon-position="after">{{ __('Next') }}</x-filament::button>
        <x-filament::input.wrapper class="w-48">
            <x-filament::input.select wire:model.live="branchId">
                <option value="">{{ __('All branches') }}</option>
                @foreach ($this->getBranchOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::button color="gray" size="sm" wire:click="copyPreviousWeek" icon="heroicon-o-document-duplicate">{{ __('Copy last week') }}</x-filament::button>
        <x-filament::button size="sm" wire:click="save" icon="heroicon-o-check-circle">{{ __('Publish') }}</x-filament::button>
    </div>

    @php($shifts = $this->getShiftOptions())
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="p-2">{{ __('Employee') }}</th>
                    @foreach ($this->getDates() as $d)<th class="p-2">{{ \Carbon\Carbon::parse($d)->format('D d') }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->getEmployees() as $e)
                    <tr wire:key="r-{{ $e->id }}" class="border-t border-gray-100 dark:border-white/5">
                        <td class="whitespace-nowrap p-2">{{ $e->code }} · {{ $e->fullName() }}</td>
                        @foreach ($this->getDates() as $d)
                            <td class="p-1">
                                <select wire:model="grid.{{ $e->id }}.{{ $d }}" class="w-full rounded-md border-gray-300 text-xs dark:border-white/10 dark:bg-gray-800">
                                    <option value="">{{ __('normal') }}</option>
                                    @foreach ($shifts as $id => $code)<option value="{{ $id }}">{{ $code }}</option>@endforeach
                                </select>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-sm text-gray-500">{{ __('"normal" = the employee\'s regular shift assignment. Publishing recalculates the affected days.') }}</p>
</x-filament-panels::page>
