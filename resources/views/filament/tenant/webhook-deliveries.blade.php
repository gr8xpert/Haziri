@php($tz = tenant('timezone') ?? config('hazri.default_timezone'))
<div class="hz-table-wrap">
    <table class="hz-table">
        <thead>
            <tr>
                <th>{{ __('Time') }}</th>
                <th>{{ __('Event') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-end">{{ __('HTTP code') }}</th>
                <th class="text-end">{{ __('Tries') }}</th>
                <th>{{ __('Error / response') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($deliveries as $delivery)
                <tr wire:key="delivery-{{ $delivery->id }}">
                    <td class="whitespace-nowrap">{{ $delivery->created_at?->setTimezone($tz)->format('d M Y H:i') }}</td>
                    <td class="font-mono text-xs">{{ $delivery->event }}</td>
                    <td>
                        <x-filament::badge :color="match ($delivery->status) { 'delivered' => 'success', 'failed' => 'danger', default => 'warning' }">
                            {{ match ($delivery->status) { 'delivered' => __('Delivered'), 'failed' => __('Failed'), default => __('Waiting / retrying') } }}
                        </x-filament::badge>
                    </td>
                    <td class="text-end">{{ $delivery->response_status ?? '—' }}</td>
                    <td class="text-end">{{ $delivery->attempts }}</td>
                    <td class="max-w-xs truncate text-gray-500 dark:text-gray-400" title="{{ $delivery->response_body }}">{{ \Illuminate\Support\Str::limit((string) $delivery->response_body, 80) ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="hz-empty">
                            <x-filament::icon icon="heroicon-o-queue-list" class="hz-empty-icon" />
                            <strong>{{ __('No deliveries yet') }}</strong>
                            <span>{{ __('Use Send test to try this webhook.') }}</span>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
