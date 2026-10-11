{{-- The guide's topics by group, for the side of a topic page. --}}
<nav class="space-y-6 text-sm">
    @foreach ($groups as $group => $pages)
        <div>
            <div class="mb-1.5 px-3 text-xs font-semibold tracking-wide text-zinc-500 uppercase">{{ $group }}</div>
            @foreach ($pages as $p)
                <a href="/help/{{ $p->slug }}" @class([
                    'block rounded-lg px-3 py-1.5 transition',
                    'bg-brand-soft font-medium text-brand dark:bg-emerald-400/10 dark:text-emerald-300' => isset($page) && $page->slug === $p->slug,
                    'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white' => ! isset($page) || $page->slug !== $p->slug,
                ])>{{ \App\Support\Help\HelpLibrary::brand($p->title) }}</a>
            @endforeach
        </div>
    @endforeach
</nav>
