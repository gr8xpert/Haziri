<x-filament-panels::page>
    <form wire:submit="check" class="hz-card flex flex-col gap-4 p-4 sm:p-5">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit" icon="heroicon-m-magnifying-glass" wire:loading.attr="disabled" wire:target="check">
                {{ __('Check file') }}
            </x-filament::button>
        </div>
    </form>

    @if ($preview)
        <x-filament::section :heading="__('Match your columns')" icon="heroicon-o-arrows-right-left"
            :description="__('We guessed which column is which. Check each one; your choices are remembered for the next upload.')">
            @php($missing = $this->getMissingRequired())
            @if ($missing)
                <p class="mb-4 rounded-lg bg-warning-50 p-3 text-sm text-warning-800 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300">
                    {{ __('Still needed: :fields.', ['fields' => implode(', ', $missing)]) }}
                </p>
            @endif
            <div class="hz-table-wrap shadow-none!">
                <table class="hz-table">
                    <thead>
                        <tr><th>{{ __('Your column') }}</th><th>{{ __('First row') }}</th><th>{{ __('Haziri field') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['headers'] as $i => $header)
                            <tr wire:key="col-{{ $i }}">
                                <td class="font-medium text-gray-950 dark:text-white">{{ $header !== '' ? $header : __('(no heading)') }}</td>
                                <td class="max-w-56 truncate text-gray-500 dark:text-gray-400">{{ $preview['sample'][strtolower($header)] ?? '' }}</td>
                                <td class="w-72">
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select wire:model.live="mapping.{{ $i }}">
                                            <option value="">{{ __('— Skip this column —') }}</option>
                                            @foreach ($this->getFieldOptions() as $field => $label)
                                                <option value="{{ $field }}">{{ $label }}{{ in_array($field, \App\Services\Employees\EmployeeImporter::REQUIRED, true) ? ' *' : '' }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-4">
                <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <x-filament::input.checkbox wire:model.live="createMissing" />
                    {{ __('Create missing departments') }}
                </label>
                @if ($this->canSuggestWithAi())
                    <x-filament::button color="gray" size="sm" icon="heroicon-m-sparkles" wire:click="suggestWithAi" wire:loading.attr="disabled">
                        {{ __('Suggest the rest with AI (only the headings are sent)') }}
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('Preview')" icon="heroicon-o-eye">
            <dl @class(['grid gap-6', 'grid-cols-3 sm:max-w-lg' => ! ($preview['restores'] ?? 0), 'grid-cols-2 sm:grid-cols-4 sm:max-w-2xl' => $preview['restores'] ?? 0])>
                <div class="hz-kpi"><dt>{{ __('New') }}</dt><dd>{{ $preview['creates'] }}</dd></div>
                <div class="hz-kpi"><dt>{{ __('Updates') }}</dt><dd>{{ $preview['updates'] }}</dd></div>
                @if ($preview['restores'] ?? 0)
                    <div class="hz-kpi"><dt>{{ __('Restored') }}</dt><dd>{{ $preview['restores'] }}</dd></div>
                @endif
                <div class="hz-kpi"><dt>{{ __('Errors') }}</dt><dd @class(['text-danger-600! dark:text-danger-400!' => count($preview['errors'])])>{{ count($preview['errors']) }}</dd></div>
            </dl>

            @if ($preview['over_limit'] ?? 0)
                <p class="mt-5 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 ring-1 ring-danger-600/15 dark:bg-danger-500/10 dark:text-danger-400 dark:ring-danger-400/20">
                    {{ __('Your plan has room for :left more active employees, but this file adds :adds. Remove :over new rows, mark people who left as left, or upgrade the plan; otherwise nothing is imported.', ['left' => $preview['capacity_left'], 'adds' => $preview['capacity_left'] + $preview['over_limit'], 'over' => $preview['over_limit']]) }}
                </p>
            @endif

            @if ($preview['restores'] ?? 0)
                <p class="mt-5 text-sm text-gray-700 dark:text-gray-300">
                    {{ __(':n rows match deleted employees. They will be restored and updated from the file.', ['n' => $preview['restores']]) }}
                </p>
            @endif

            @if ($preview['new_departments'])
                <p class="mt-5 text-sm text-gray-700 dark:text-gray-300">
                    {{ __('New departments that will be created: :list', ['list' => implode(', ', $preview['new_departments'])]) }}
                </p>
            @endif

            @if ($preview['errors'])
                <div class="mt-5 rounded-lg bg-danger-50 p-4 ring-1 ring-danger-600/15 dark:bg-danger-500/10 dark:ring-danger-400/20">
                    <ul class="space-y-1 text-sm text-danger-700 dark:text-danger-400">
                        @foreach ($preview['errors'] as $line => $messages)
                            <li><strong class="font-semibold">{{ __('Row :n', ['n' => $line]) }}:</strong> {{ implode(' ', $messages) }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-sm text-danger-700/80 dark:text-danger-300/80">{{ __('Rows with errors are skipped. Fix them and upload again, or import the valid rows now.') }}</p>
                </div>
            @endif

            @if ($preview['rows'])
                <div class="hz-table-wrap mt-5 shadow-none!">
                    <table class="hz-table">
                        <thead>
                            <tr>
                                <th>{{ __('Code') }}</th><th>{{ __('Name') }}</th>
                                <th>{{ __('PIN') }}</th><th>{{ __('Joined') }}</th><th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($preview['rows'], 0, 50) as $row)
                                <tr>
                                    <td class="text-gray-500 dark:text-gray-400">{{ $row['code'] }}</td>
                                    <td class="font-medium text-gray-950 dark:text-white">{{ trim($row['first_name'] . ' ' . $row['last_name']) }}</td>
                                    <td>{{ $row['device_pin'] }}</td>
                                    <td class="whitespace-nowrap">{{ $row['joined_on'] }}</td>
                                    <td>
                                        @if ($row['_deleted'] ?? false)
                                            <x-filament::badge color="warning">{{ __('Restore') }}</x-filament::badge>
                                        @else
                                            <x-filament::badge :color="$row['_exists'] ? 'info' : 'success'">{{ $row['_exists'] ? __('Update') : __('New') }}</x-filament::badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if (count($preview['rows']) > 50)
                    <p class="hz-hint mt-2">{{ __('Showing the first 50 of :n rows.', ['n' => count($preview['rows'])]) }}</p>
                @endif
                <x-filament::button class="mt-5" wire:click="import" icon="heroicon-m-arrow-down-tray">
                    {{ __('Import :n rows', ['n' => count($preview['rows'])]) }}
                </x-filament::button>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
