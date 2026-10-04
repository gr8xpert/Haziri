{{-- Evidence behind an anomaly flag (AnomalyResource::evidence()). --}}
<div class="flex flex-col gap-4 text-sm">
    @if ($explanation)
        <p class="text-gray-700 dark:text-gray-300">{{ $explanation }}</p>
    @endif

    @if ($facts)
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
            @foreach ($facts as $label => $value)
                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $value }}</dd>
            @endforeach
        </dl>
    @endif

    @if ($punches)
        <div class="hz-table-wrap max-h-80 shadow-none!">
            <table class="hz-table hz-table-dense">
                <thead>
                    <tr><th>{{ __('Date') }}</th><th>{{ __('Time') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Machine / place') }}</th><th>{{ __('Source') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($punches as $p)
                        <tr>
                            <td class="whitespace-nowrap">{{ $p['date'] }}</td>
                            <td class="tabular-nums">{{ $p['time'] }}</td>
                            <td class="font-medium text-gray-950 dark:text-white">{{ $p['employee'] }}</td>
                            <td>{{ $p['where'] }}</td>
                            <td>{{ $p['source'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="hz-hint">{{ __('Check the punch photos or CCTV for these times before deciding.') }}</p>
    @elseif ($facts === [])
        <p class="hz-hint">{{ __('No further details were recorded for this flag.') }}</p>
    @endif
</div>
