<x-filament-panels::page>
    <div class="hz-card p-4 sm:p-5">
        {{ $this->form }}
    </div>

    @php
        $data = $this->getReportData();
        $isDayCol = fn (string $key): bool => (bool) preg_match('/^d\d{1,2}$/', $key);
        $tone = function (?string $code): string {
            $code = rtrim((string) $code, '½');

            return match (true) {
                $code === '' => '',
                in_array($code, ['P', 'OD', 'WFH', 'EX'], true) => 'hz-code-ok',
                in_array($code, ['L', 'HD', 'MO'], true) => 'hz-code-warn',
                in_array($code, ['A', 'UL'], true) => 'hz-code-bad',
                in_array($code, ['WO', 'H', '–', '-'], true) => 'hz-code-off',
                default => 'hz-code-leave',
            };
        };
        $dense = count($data['columns']) > 12;
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $data['subtitle'] }}</p>
        @if ($data['columns'])
            <p class="hz-hint">{{ trans_choice(':n row|:n rows', count($data['rows']), ['n' => number_format(count($data['rows']))]) }}</p>
        @endif
    </div>

    @if ($data['columns'])
        <div class="hz-table-wrap max-h-[70vh]" wire:loading.class="opacity-60">
            <table @class(['hz-table', 'hz-table-dense' => $dense])>
                <thead>
                    <tr>
                        @foreach ($data['columns'] as $key => $label)
                            <th @class(['text-center' => $isDayCol($key)])>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['rows'] as $row)
                        <tr>
                            @foreach (array_keys($data['columns']) as $key)
                                @php($value = $row[$key] ?? '')
                                @if ($isDayCol($key))
                                    <td class="text-center">
                                        @if ($value !== '')
                                            <span class="hz-code {{ $tone($value) }}">{{ $value }}</span>
                                        @endif
                                    </td>
                                @else
                                    <td @class(['font-medium text-gray-950 dark:text-white' => $loop->index === 1, 'whitespace-nowrap'])>
                                        @if ($loop->index === 1 && filled($row['_url'] ?? null))
                                            <a href="{{ $row['_url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $value }}</a>
                                        @else
                                            {{ \App\Reports\CellFormat::show($value) }}
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($data['columns']) }}">
                                <div class="hz-empty">
                                    <x-filament::icon icon="heroicon-o-document-magnifying-glass" class="hz-empty-icon" />
                                    <strong>{{ __('No data for these filters') }}</strong>
                                    <span>{{ __('Widen the date range or clear a filter.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="hz-card">
            <div class="hz-empty">
                <x-filament::icon icon="heroicon-o-adjustments-horizontal" class="hz-empty-icon" />
                <strong>{{ __('Choose the filters above') }}</strong>
                <span>{{ __('The report appears here as soon as the required filters are set.') }}</span>
            </div>
        </div>
    @endif

    @if ($data['summary'])
        <dl class="flex flex-wrap gap-2 text-sm">
            @foreach ($data['summary'] as $k => $v)
                <div class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1 ring-1 ring-gray-950/[0.07] dark:bg-white/5 dark:ring-white/10">
                    <dt class="text-gray-500 dark:text-gray-400">{{ $k }}</dt>
                    <dd class="font-semibold text-gray-950 tabular-nums dark:text-white">{{ $v }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</x-filament-panels::page>
