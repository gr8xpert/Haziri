@php($brand = \App\Support\Brand::name())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="@hasSection('description')@yield('description')@else{{ __('How to use :app: every page explained, quick reference and troubleshooting.', ['app' => $brand]) }}@endif">
    <link rel="icon" href="{{ \App\Support\Brand::iconUrl() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ __(':app help', ['app' => $brand]) }}</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">

<header class="sticky top-0 z-30 border-b border-zinc-950/[0.07] bg-white/90 backdrop-blur-lg dark:border-white/[0.07] dark:bg-zinc-950/90">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
        <a href="/help" class="flex shrink-0 items-center gap-2.5 font-semibold tracking-tight">
            <img src="{{ \App\Support\Brand::iconUrl() }}" alt="" class="size-8 rounded-lg">
            <span class="hidden sm:inline">{{ $brand }}</span> <span class="font-normal text-zinc-500">{{ __('Help') }}</span>
        </a>

        {{-- Search: every section of every topic, matched in the browser (index from /help/search.json). --}}
        <div class="relative ms-auto w-full max-w-md" id="hz-search">
            <label for="hz-q" class="sr-only">{{ __('Search the help') }}</label>
            <input id="hz-q" type="search" autocomplete="off" placeholder="{{ __('Search: leave, late, payroll, machine offline…') }}"
                   class="w-full rounded-xl border-0 bg-zinc-100 px-4 py-2.5 text-sm ring-1 ring-transparent outline-none placeholder:text-zinc-500 focus:bg-white focus:ring-brand dark:bg-white/10 dark:focus:bg-zinc-900">
            <div id="hz-results" class="absolute inset-x-0 top-full mt-2 hidden max-h-[70vh] overflow-y-auto rounded-xl bg-white p-1.5 shadow-xl ring-1 ring-zinc-950/10 dark:bg-zinc-900 dark:ring-white/10"></div>
        </div>

        <nav class="hidden shrink-0 items-center gap-1 text-sm lg:flex">
            @foreach (['/help' => __('Guide'), '/help/quick' => __('Quick reference'), '/help/troubleshooting' => __('Troubleshooting')] as $href => $label)
                <a href="{{ $href }}" @class(['rounded-lg px-3 py-2 transition', 'bg-brand-soft font-medium text-brand dark:bg-emerald-400/10 dark:text-emerald-300' => request()->is(ltrim($href, '/')), 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-white/5' => ! request()->is(ltrim($href, '/'))])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
    <nav class="flex gap-1 overflow-x-auto px-4 pb-2 text-sm lg:hidden">
        <a href="/help" class="shrink-0 rounded-lg px-3 py-1.5 text-zinc-600 dark:text-zinc-400">{{ __('Guide') }}</a>
        <a href="/help/quick" class="shrink-0 rounded-lg px-3 py-1.5 text-zinc-600 dark:text-zinc-400">{{ __('Quick reference') }}</a>
        <a href="/help/troubleshooting" class="shrink-0 rounded-lg px-3 py-1.5 text-zinc-600 dark:text-zinc-400">{{ __('Troubleshooting') }}</a>
    </nav>
</header>

@yield('content')

<footer class="mt-20 border-t border-zinc-950/[0.07] py-8 text-center text-sm text-zinc-500 dark:border-white/[0.07]">
    {{ __('Can\'t find it? Ask your :app administrator, or use the search at the top.', ['app' => $brand]) }}
</footer>

<script>
(() => {
    const input = document.getElementById('hz-q');
    const box = document.getElementById('hz-results');
    let index = null;
    const esc = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const norm = (s) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '');
    const load = () => index ??= fetch('/help/search.json').then((r) => r.json());

    function snippet(text, words) {
        const t = norm(text);
        let at = -1;
        for (const w of words) { at = t.indexOf(w); if (at >= 0) break; }
        let s = text.slice(Math.max(0, at - 60), Math.max(0, at - 60) + 180);
        s = esc((at > 60 ? '…' : '') + s + (text.length > at + 120 ? '…' : ''));
        for (const w of words) s = s.replace(new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark>$1</mark>');
        return s;
    }

    async function search() {
        const q = norm(input.value.trim());
        if (q.length < 2) { box.classList.add('hidden'); return; }
        const words = q.split(/\s+/).filter((w) => w.length > 1);
        const hits = (await load()).map((e) => {
            const title = norm(e.p), head = norm(e.h), text = norm(e.x);
            let score = 0;
            for (const w of words) {
                const s = (title.includes(w) ? 6 : 0) + (head.includes(w) ? 4 : 0) + (text.includes(w) ? 1 : 0);
                if (!s) return null;
                score += s;
            }
            if ((title + ' ' + head).includes(q)) score += 8;
            return { e, score };
        }).filter(Boolean).sort((a, b) => b.score - a.score).slice(0, 12);

        box.innerHTML = hits.length
            ? hits.map(({ e }) => `<a href="${e.u}" class="block rounded-lg px-3 py-2.5 hover:bg-zinc-100 dark:hover:bg-white/5">
                <div class="text-sm font-medium">${esc(e.p)}${e.h ? ' <span class="text-zinc-400">›</span> ' + esc(e.h) : ''}</div>
                <div class="mt-0.5 text-xs text-zinc-500">${esc(e.g)}</div>
                <div class="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">${snippet(e.x, words)}</div></a>`).join('')
            : `<div class="px-3 py-4 text-sm text-zinc-500">{{ __('Nothing found. Try another word, e.g. "leave" or "machine".') }}</div>`;
        box.classList.remove('hidden');
    }

    input.addEventListener('focus', load, { once: true });
    input.addEventListener('input', search);
    input.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') { const first = box.querySelector('a'); if (first) location.href = first.href; }
        if (ev.key === 'Escape') { box.classList.add('hidden'); input.blur(); }
    });
    document.addEventListener('click', (ev) => { if (!document.getElementById('hz-search').contains(ev.target)) box.classList.add('hidden'); });
    document.addEventListener('keydown', (ev) => {
        if (ev.key === '/' && document.activeElement !== input && !/INPUT|TEXTAREA/.test(document.activeElement.tagName)) { ev.preventDefault(); input.focus(); }
    });
})();
</script>
</body>
</html>
