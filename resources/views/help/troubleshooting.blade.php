@extends('help.layout')
@section('title', __('Troubleshooting'))

@php($withProblems = $groups->map(fn ($pages) => $pages->filter(fn ($p) => $p->problems() !== []))->filter(fn ($pages) => $pages->isNotEmpty()))
@php($total = $withProblems->flatten(1)->sum(fn ($p) => count($p->problems())))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
    <h1 class="text-3xl font-semibold tracking-tight md:text-4xl">{{ __('Something not working?') }}</h1>
    <p class="mt-3 max-w-[60ch] text-lg text-zinc-600 dark:text-zinc-400">{{ __('Find your problem below and open it to see the fix. Type a word from the message you see on screen to narrow the list.') }}</p>

    <div class="sticky top-[6.5rem] z-10 -mx-4 mt-6 bg-white/95 px-4 py-3 backdrop-blur-sm sm:-mx-6 sm:px-6 lg:top-16 dark:bg-zinc-950/95">
        <label for="hz-filter" class="sr-only">{{ __('Filter the problems') }}</label>
        <div class="flex items-center gap-3 rounded-xl bg-zinc-100 px-4 ring-1 ring-transparent transition focus-within:bg-white focus-within:ring-brand dark:bg-white/10 dark:focus-within:bg-zinc-900 dark:focus-within:ring-emerald-400">
            @svg('heroicon-m-funnel', 'size-5 shrink-0 text-zinc-400')
            <input id="hz-filter" type="search" autocomplete="off" placeholder="{{ __('Filter: offline, password, absent, late…') }}" class="min-w-0 flex-1 bg-transparent py-3 text-base outline-none placeholder:text-zinc-500">
            <span id="hz-filter-count" class="shrink-0 text-xs text-zinc-500 tabular-nums" aria-live="polite">{{ trans_choice('{1} :count problem|[2,*] :count problems', $total, ['count' => $total]) }}</span>
        </div>
    </div>

    <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="{{ __('Jump to a part of the guide') }}" id="hz-groups">
        @foreach ($withProblems as $group => $pages)
            <a href="#{{ \Illuminate\Support\Str::slug($group) }}" data-group="{{ \Illuminate\Support\Str::slug($group) }}" class="rounded-full px-3 py-1 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">{{ $group }}</a>
        @endforeach
    </nav>

    <div id="hz-problems">
        @foreach ($withProblems as $group => $pages)
            <section id="{{ \Illuminate\Support\Str::slug($group) }}" data-group="{{ \Illuminate\Support\Str::slug($group) }}" class="mt-12 scroll-mt-40">
                <h2 class="text-xl font-semibold">{{ $group }}</h2>
                @foreach ($pages as $p)
                    <div id="{{ $p->slug }}" class="mt-5 scroll-mt-40" data-topic>
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 class="text-sm font-medium text-zinc-500">{{ \App\Support\Help\HelpLibrary::brand($p->title) }}</h3>
                            <a href="/help/{{ $p->slug }}" class="shrink-0 text-xs text-brand hover:underline dark:text-emerald-300">{{ __('Open the page') }}</a>
                        </div>
                        <div class="mt-2 divide-y divide-zinc-950/[0.07] rounded-xl ring-1 ring-zinc-950/10 dark:divide-white/[0.07] dark:ring-white/10">
                            @foreach ($p->problems() as $problem)
                                <details id="{{ $p->slug }}-{{ $problem['anchor'] }}" class="group scroll-mt-40" data-problem>
                                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 font-medium transition hover:bg-zinc-50 dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition group-open:rotate-45 group-open:bg-brand group-open:text-white dark:bg-white/10 dark:group-open:bg-emerald-500 dark:group-open:text-emerald-950" aria-hidden="true">@svg('heroicon-m-plus', 'size-3.5')</span>
                                        <span class="flex-1">{{ \App\Support\Help\HelpLibrary::brand($problem['question']) }}</span>
                                    </summary>
                                    <div class="hz-prose hz-prose-sm border-t border-zinc-950/[0.05] bg-zinc-50 px-4 py-4 ps-13 dark:border-white/[0.05] dark:bg-white/[0.03]">{!! \App\Support\Help\HelpLibrary::render($problem['answer']) !!}</div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
        <p id="hz-none" class="mt-12 hidden rounded-xl bg-zinc-50 p-6 text-center text-zinc-600 dark:bg-white/5 dark:text-zinc-400">{{ __('No problem matches that word. Try the search at the top, which looks through the whole guide.') }}</p>
    </div>
</div>

<script>
(() => {
    const input = document.getElementById('hz-filter');
    const count = document.getElementById('hz-filter-count');
    const none = document.getElementById('hz-none');
    const problems = [...document.querySelectorAll('[data-problem]')];
    const norm = (s) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '');
    const texts = problems.map((d) => norm(d.textContent));
    const labelOne = @json(__(':count problem')), labelMany = @json(__(':count problems'));

    function apply() {
        const q = norm(input.value.trim());
        let shown = 0;
        problems.forEach((d, i) => { const hit = !q || texts[i].includes(q); d.hidden = !hit; if (hit) shown++; if (q && hit && q.length > 2) d.open = true; });
        document.querySelectorAll('[data-topic]').forEach((t) => t.hidden = ![...t.querySelectorAll('[data-problem]')].some((d) => !d.hidden));
        const liveGroups = new Set();
        document.querySelectorAll('section[data-group]').forEach((s) => { const live = [...s.querySelectorAll('[data-topic]')].some((t) => !t.hidden); s.hidden = !live; if (live) liveGroups.add(s.dataset.group); });
        document.querySelectorAll('#hz-groups a').forEach((a) => a.hidden = !liveGroups.has(a.dataset.group));
        none.hidden = shown > 0;
        count.textContent = (shown === 1 ? labelOne : labelMany).replace(':count', shown);
    }

    input.addEventListener('input', apply);
    const q = new URLSearchParams(location.search).get('q');
    if (q) { input.value = q; apply(); }
    // A link to one problem (…#slug-anchor) opens it.
    if (location.hash) { const d = document.getElementById(location.hash.slice(1)); if (d?.matches('details')) d.open = true; }
})();
</script>
@endsection
