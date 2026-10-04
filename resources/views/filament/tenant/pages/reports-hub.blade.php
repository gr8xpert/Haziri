<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        @foreach ($this->getGroups() as $group => $reports)
            <section aria-labelledby="rg-{{ $loop->index }}">
                <h2 id="rg-{{ $loop->index }}" class="mb-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $group }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $report)
                        <a href="{{ $report['url'] }}" class="hz-card group flex flex-col gap-1 p-4 transition hover:ring-primary-500/40">
                            <span class="font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">{{ $report['title'] }}</span>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $report['description'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
