@extends('help.layout')
@section('title', __('Quick reference'))

@section('content')
<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
    <h1 class="text-3xl font-semibold tracking-tight md:text-4xl">{{ __('Quick reference') }}</h1>
    <p class="mt-3 max-w-[60ch] text-lg text-zinc-600 dark:text-zinc-400">{{ __('Every page in a few lines: what it is for, where it is in the menu, and the main points. Open a topic for the full guide with pictures.') }}</p>

    <nav class="sticky top-[6.5rem] z-10 -mx-4 mt-6 flex gap-2 overflow-x-auto bg-white/95 px-4 py-3 text-sm backdrop-blur-sm sm:-mx-6 sm:px-6 lg:top-16 dark:bg-zinc-950/95" aria-label="{{ __('Jump to a group') }}">
        @foreach ($groups as $group => $pages)
            <a href="#{{ \Illuminate\Support\Str::slug($group) }}" class="shrink-0 rounded-full px-3 py-1 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">{{ $group }}</a>
        @endforeach
    </nav>

    @foreach ($groups as $group => $pages)
        <section id="{{ \Illuminate\Support\Str::slug($group) }}" class="mt-12 scroll-mt-36 lg:scroll-mt-32">
            <h2 class="border-b border-zinc-950/[0.07] pb-2 text-xl font-semibold dark:border-white/[0.07]">{{ $group }}</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($pages as $p)
                    <article class="flex flex-col rounded-xl p-5 ring-1 ring-zinc-950/10 dark:ring-white/10">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3><a href="/help/{{ $p->slug }}" class="font-semibold hover:text-brand dark:hover:text-emerald-300">{{ \App\Support\Help\HelpLibrary::brand($p->title) }}</a></h3>
                            <a href="/help/{{ $p->slug }}" class="shrink-0 text-xs text-brand hover:underline dark:text-emerald-300">{{ __('Full guide') }}</a>
                        </div>
                        @if ($p->menu)
                            <div class="mt-1 flex items-center gap-1 text-xs text-zinc-500">@svg('heroicon-m-map-pin', 'size-3 shrink-0') {{ \App\Support\Help\HelpLibrary::brand($p->menu) }}</div>
                        @endif
                        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">{{ \App\Support\Help\HelpLibrary::brand($p->summary) }}</p>
                        @if ($short = $p->section(\App\Support\Help\HelpLibrary::QUICK))
                            <div class="hz-prose hz-prose-sm mt-3 border-t border-zinc-950/[0.05] pt-3 dark:border-white/[0.06]">{!! \App\Support\Help\HelpLibrary::render($short) !!}</div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
@endsection
