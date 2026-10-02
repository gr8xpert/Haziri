<x-filament-panels::page>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->getCompanies() as $id => $name)
            <a href="{{ route('tenant.switch', $id) }}"
               class="hz-card group flex items-center gap-4 p-5 transition hover:ring-primary-500/50 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-base font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
                    {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-gray-950 dark:text-white">{{ $name }}</span>
                    <span class="block text-sm text-gray-500 dark:text-gray-400">{{ __('Open this company') }}</span>
                </span>
                <x-filament::icon icon="heroicon-m-arrow-right" class="size-5 text-gray-400 transition-transform motion-safe:group-hover:translate-x-0.5 rtl:rotate-180" />
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
