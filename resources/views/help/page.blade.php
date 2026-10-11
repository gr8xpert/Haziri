@extends('help.layout')
@section('title', \App\Support\Help\HelpLibrary::brand($page->title))
@section('description', \App\Support\Help\HelpLibrary::brand($page->summary))

@php($problems = $page->problems())

@section('content')
<div class="mx-auto grid max-w-7xl gap-10 px-4 py-8 sm:px-6 lg:grid-cols-[15rem_minmax(0,1fr)] xl:grid-cols-[15rem_minmax(0,1fr)_13rem]">
    <aside class="hidden lg:block">
        <div class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto pe-2">@include('help._nav')</div>
    </aside>

    <article class="min-w-0">
        {{-- Small screens: the topic list and the page contents fold away under the title. --}}
        <nav class="mb-6 flex flex-wrap gap-2 text-sm lg:hidden" aria-label="{{ __('Browse') }}">
            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg px-3 py-2 ring-1 ring-zinc-950/10 dark:ring-white/15 [&::-webkit-details-marker]:hidden">
                    @svg('heroicon-m-bars-3', 'size-4 text-zinc-500') {{ __('All topics') }} @svg('heroicon-m-chevron-down', 'size-4 text-zinc-400 transition group-open:rotate-180')
                </summary>
                <div class="absolute start-0 z-20 mt-2 max-h-[60vh] w-72 overflow-y-auto rounded-xl bg-white p-3 shadow-xl ring-1 ring-zinc-950/10 dark:bg-zinc-900 dark:ring-white/10">@include('help._nav')</div>
            </details>
            @if (count($page->contents()) > 1)
                <details class="group relative xl:hidden">
                    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg px-3 py-2 ring-1 ring-zinc-950/10 dark:ring-white/15 [&::-webkit-details-marker]:hidden">
                        {{ __('On this page') }} @svg('heroicon-m-chevron-down', 'size-4 text-zinc-400 transition group-open:rotate-180')
                    </summary>
                    <div class="absolute start-0 z-20 mt-2 w-72 rounded-xl bg-white p-2 shadow-xl ring-1 ring-zinc-950/10 dark:bg-zinc-900 dark:ring-white/10">
                        @foreach ($page->contents() as $c)
                            <a href="#{{ $c['anchor'] }}" class="block rounded-lg px-3 py-1.5 text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/5">{{ $c['heading'] }}</a>
                        @endforeach
                    </div>
                </details>
            @endif
        </nav>

        <nav class="flex flex-wrap items-center gap-1.5 text-sm text-zinc-500" aria-label="{{ __('Breadcrumb') }}">
            <a href="/help" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Guide') }}</a>
            @svg('heroicon-m-chevron-right', 'size-3.5 rtl:rotate-180')
            <span>{{ $page->group }}</span>
        </nav>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-balance md:text-4xl">{{ \App\Support\Help\HelpLibrary::brand($page->title) }}</h1>
        @if ($page->summary)
            <p class="mt-3 max-w-[65ch] text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">{{ \App\Support\Help\HelpLibrary::brand($page->summary) }}</p>
        @endif
        @if ($page->menu)
            <p class="mt-5 inline-flex max-w-full flex-wrap items-center gap-2 rounded-xl bg-zinc-100 px-3.5 py-2 text-sm dark:bg-white/10">
                @svg('heroicon-m-map-pin', 'size-4 shrink-0 text-brand dark:text-emerald-400')
                <span class="text-zinc-500">{{ __('Where to find it:') }}</span> <span class="font-medium">{{ \App\Support\Help\HelpLibrary::brand($page->menu) }}</span>
            </p>
        @endif

        <div class="hz-prose mt-8">{!! $page->html() !!}</div>

        <div class="mt-14 border-t border-zinc-950/[0.07] pt-6 dark:border-white/[0.07]">
            @if ($problems !== [])
                <a href="/help/troubleshooting#{{ $page->slug }}" class="mb-6 flex items-start gap-3 rounded-2xl bg-emerald-50 p-4 ring-1 ring-brand/10 transition hover:bg-emerald-100/70 dark:bg-emerald-500/[0.07] dark:ring-emerald-400/15 dark:hover:bg-emerald-500/10">
                    @svg('heroicon-o-wrench-screwdriver', 'size-5 shrink-0 text-brand dark:text-emerald-400')
                    <span class="text-sm"><span class="font-semibold">{{ __('Something not working here?') }}</span> <span class="text-zinc-600 dark:text-zinc-400">{{ trans_choice('{1} There is :count common problem for this page, with its fix.|[2,*] There are :count common problems for this page, each with its fix.', count($problems), ['count' => count($problems)]) }}</span></span>
                </a>
            @endif
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    @if ($previous)
                        <a href="/help/{{ $previous->slug }}" class="block rounded-xl p-4 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">
                            <div class="flex items-center gap-1 text-xs text-zinc-500">@svg('heroicon-m-arrow-left', 'size-3 rtl:rotate-180') {{ __('Previous') }}</div>
                            <div class="mt-1 font-medium">{{ \App\Support\Help\HelpLibrary::brand($previous->title) }}</div>
                        </a>
                    @endif
                </div>
                <div>
                    @if ($next)
                        <a href="/help/{{ $next->slug }}" class="block rounded-xl p-4 text-end ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">
                            <div class="flex items-center justify-end gap-1 text-xs text-zinc-500">{{ __('Next') }} @svg('heroicon-m-arrow-right', 'size-3 rtl:rotate-180')</div>
                            <div class="mt-1 font-medium">{{ \App\Support\Help\HelpLibrary::brand($next->title) }}</div>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </article>

    @if (count($page->contents()) > 1)
        <aside class="hidden xl:block">
            <nav class="sticky top-24 text-sm" aria-label="{{ __('On this page') }}">
                <div class="mb-2 font-semibold">{{ __('On this page') }}</div>
                @foreach ($page->contents() as $c)
                    <a href="#{{ $c['anchor'] }}" class="block border-s-2 border-transparent py-1 ps-3 text-zinc-600 hover:border-brand hover:text-brand dark:text-zinc-400 dark:hover:border-emerald-400 dark:hover:text-emerald-300">{{ $c['heading'] }}</a>
                @endforeach
            </nav>
        </aside>
    @endif
</div>
@endsection
