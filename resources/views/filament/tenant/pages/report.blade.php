<x-filament-panels::page>
    {{ $this->form }}

    @php($data = $this->getReportData())

    <div class="text-sm text-gray-500">{{ $data['subtitle'] }}</div>

    @if ($data['columns'])
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-gray-500">
                        @foreach ($data['columns'] as $label)
                            <th class="whitespace-nowrap px-2 py-2">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['rows'] as $row)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            @foreach (array_keys($data['columns']) as $key)
                                <td class="whitespace-nowrap px-2 py-1.5">{{ $row[$key] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($data['columns']) }}" class="p-6 text-center text-gray-500">{{ __('No data for these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($data['summary'])
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
            @foreach ($data['summary'] as $k => $v)
                <span><span class="text-gray-500">{{ $k }}:</span> <strong>{{ $v }}</strong></span>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
