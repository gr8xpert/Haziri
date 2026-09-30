<x-filament-panels::page>
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($this->getCompanies() as $id => $name)
            <a href="{{ route('tenant.switch', $id) }}" class="block rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10">
                <div class="font-semibold">{{ $name }}</div>
                <div class="text-sm text-gray-500">{{ __('Open this company') }} →</div>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
