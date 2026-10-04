<x-filament-panels::page>
    @php
        $s = $this->getStatement();
        $tone = fn (string $t): string => match ($t) {
            'success' => 'hz-code-ok', 'warning' => 'hz-code-warn', 'danger' => 'hz-code-bad', 'gray' => 'hz-code-off', default => 'hz-code-leave',
        };
        $canExport = auth()->user()?->can('reports.export');
    @endphp

    {{-- Filters and downloads --}}
    <div class="hz-card p-4 sm:p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="flex flex-col gap-1.5">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Month') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="month" wire:model.live="month" />
                </x-filament::input.wrapper>
                <span class="hz-hint">{{ $this->getPeriodLabel() }}</span>
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Branch') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="branchId">
                        <option value="">{{ __('All branches') }}</option>
                        @foreach ($this->getBranchOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Department') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="departmentId">
                        <option value="">{{ __('All departments') }}</option>
                        @foreach ($this->getDepartmentOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="flex flex-col gap-1.5">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Employee') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="employeeId">
                        <option value="">{{ __('Choose an employee…') }}</option>
                        @foreach ($this->getEmployeeOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>

        @if ($canExport)
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-950/[0.06] pt-4 dark:border-white/[0.06]">
                <x-filament::button icon="heroicon-m-document-arrow-down" wire:click="downloadPdf" :disabled="! $s">{{ __('PDF — this employee') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-table-cells" wire:click="downloadXlsx" :disabled="! $s">{{ __('Excel') }}</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-document-duplicate" wire:click="downloadAllPdf" wire:loading.attr="disabled" wire:target="downloadAllPdf">
                    {{ __('PDF — all employees in filter') }}
                </x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-archive-box-arrow-down" wire:click="downloadZip" wire:loading.attr="disabled" wire:target="downloadZip">
                    {{ __('Separate PDFs (ZIP)') }}
                </x-filament::button>
                <span class="hz-hint" wire:loading wire:target="downloadAllPdf,downloadZip">{{ __('Preparing… one page per employee.') }}</span>
                <span class="hz-hint" wire:loading.remove wire:target="downloadAllPdf,downloadZip">{{ __('More than :n employees are prepared in the background; you get a notification with the link.', ['n' => \App\Services\Reports\MonthlyAttendanceExport::SYNC_LIMIT]) }}</span>
            </div>
        @endif
    </div>

    @if (! $s)
        <div class="hz-card">
            <div class="hz-empty">
                <x-filament::icon icon="heroicon-o-user" class="hz-empty-icon" />
                <strong>{{ __('Choose an employee') }}</strong>
                <span>{{ __('Their month appears here, exactly as it prints. Or download every employee in the filter as one PDF.') }}</span>
            </div>
        </div>
    @else
        @php($e = $s['employee'])
        @php($t = $s['totals'])

        {{-- Employee --}}
        <div class="hz-card p-4 sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $e['name'] }} <span class="font-normal text-gray-500">· {{ $e['code'] }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ collect([$e['designation'], $e['department'], $e['branch']])->filter()->implode(' · ') }}</p>
                </div>
                <div class="rounded-lg bg-primary-50 px-3 py-1.5 text-sm font-medium text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300">
                    {{ $s['period']['from'] }} – {{ $s['period']['to'] }}
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="hz-hint">{{ __('Grade') }}</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $e['grade'] ?? '—' }}</dd></div>
                <div><dt class="hz-hint">{{ __('Date of joining') }}</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $e['joined'] ?? '—' }}</dd></div>
                <div><dt class="hz-hint">{{ __('Payroll ID') }}</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $e['payroll_id'] ?? '—' }}</dd></div>
                <div><dt class="hz-hint">{{ __('Total duration / OT') }}</dt><dd class="font-medium tabular-nums text-gray-950 dark:text-white">{{ $t['span_hm'] ?: '00:00' }} / {{ $t['overtime_hm'] ?: '00:00' }}</dd></div>
            </dl>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
            @foreach ([
                [__('On time'), $t['on_time'], 'hz-code-ok'], [__('Late'), $t['late'], 'hz-code-warn'], [__('Half days'), $t['half'], 'hz-code-warn'],
                [__('Absent'), $t['absent'], 'hz-code-bad'], [__('On leave'), $t['leave'], 'hz-code-leave'], [__('Holidays'), $t['holiday'], 'hz-code-leave'],
                [__('Off days'), $t['off'], 'hz-code-off'], [__('OD / WFH / exempt'), $t['other'], 'hz-code-ok'],
            ] as [$label, $value, $class])
                <div class="hz-card flex items-center justify-between gap-2 px-3 py-2.5">
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ $label }}</span>
                    <span class="hz-code {{ $class }} tabular-nums">{{ $value }}</span>
                </div>
            @endforeach
        </div>

        {{-- Days --}}
        <div class="hz-table-wrap">
            <table class="hz-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th><th>{{ __('Day') }}</th><th>{{ __('Schedule') }}</th><th>{{ __('Shift') }}</th>
                        <th>{{ __('In') }}</th><th>{{ __('Out') }}</th><th>{{ __('Status') }}</th><th>{{ __('Duration') }}</th><th>{{ __('OT') }}</th><th>{{ __('Remarks') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($s['rows'] as $r)
                        <tr @class(['opacity-60' => $r['day_type'] === 'Off Day'])>
                            <td class="whitespace-nowrap tabular-nums">{{ $r['date'] }}</td>
                            <td class="whitespace-nowrap">{{ $r['weekday'] }}</td>
                            <td class="whitespace-nowrap">{{ $r['day_type'] }}</td>
                            <td class="whitespace-nowrap tabular-nums text-gray-500">{{ $r['sched_in'] ? $r['sched_in'] . ' – ' . $r['sched_out'] : '' }}</td>
                            @foreach (['in', 'out'] as $k)
                                <td class="whitespace-nowrap tabular-nums">
                                    @if ($r[$k])
                                        <span @class(['rounded bg-amber-100 px-1 font-semibold text-amber-900 dark:bg-amber-400/20 dark:text-amber-200' => $r[$k . '_manual']])
                                              @if ($r[$k . '_manual']) title="{{ __('Entered or corrected by hand') }}" @endif>{{ $r[$k] }}</span>
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="whitespace-nowrap"><span class="hz-code {{ $tone($r['tone']) }}">{{ $r['status'] }}</span></td>
                            <td class="whitespace-nowrap tabular-nums">{{ $r['duration'] }}</td>
                            <td class="whitespace-nowrap tabular-nums">{{ $r['overtime'] }}</td>
                            <td class="text-gray-600 dark:text-gray-300">{{ $r['remarks'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="hz-hint"><span class="rounded bg-amber-100 px-1 font-semibold text-amber-900 dark:bg-amber-400/20 dark:text-amber-200">08:30</span>
            {{ __('= time entered or corrected by hand; the reason is in Remarks.') }}</p>

        {{-- Leave --}}
        @if ($s['leave'])
            <div class="hz-table-wrap">
                <table class="hz-table">
                    <thead>
                        <tr><th>{{ __('Leave') }}</th><th class="text-end">{{ __('Prev. balance') }}</th><th class="text-end">{{ __('Allotted') }}</th>
                            <th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Availed') }}</th><th class="text-end">{{ __('Adjusted (late / half day)') }}</th>
                            <th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Without pay') }}</th><th class="text-end">{{ __('Current balance') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($s['leave'] as $l)
                            <tr>
                                <td class="font-medium text-gray-950 dark:text-white">{{ $l['type'] }}</td>
                                @foreach (['previous', 'allotted', 'total', 'availed', 'adjusted', 'credit', 'lwp'] as $k)
                                    <td class="text-end tabular-nums">{{ $l[$k] ?: '–' }}</td>
                                @endforeach
                                <td class="text-end font-semibold tabular-nums text-primary-700 dark:text-primary-300">{{ $l['closing'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
