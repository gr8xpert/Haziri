@extends('help.layout')
@section('title', \App\Support\Help\HelpLibrary::brand($page->title))
@section('description', \App\Support\Help\HelpLibrary::brand($page->summary))

@section('content')
<div class="mx-auto grid max-w-7xl gap-10 px-4 py-8 sm:px-6 lg:grid-cols-[15rem_minmax(0,1fr)] xl:grid-cols-[15rem_minmax(0,1fr)_13rem]">
    <aside class="hidden lg:block">
        <div class="sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto pe-2">@include('help._nav')</div>
    </aside>

    <article class="min-w-0">
        <div class="text-sm text-zinc-500"><a href="/help" class="hover:underline">{{ __('Guide') }}</a> › {{ $page->group }}</div>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-balance">{{ \App\Support\Help\HelpLibrary::brand($page->title) }}</h1>
        @if ($page->summary)
            <p class="mt-3 text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">{{ \App\Support\Help\HelpLibrary::brand($page->summary) }}</p>
        @endif
        @if ($page->menu)
            <p class="mt-4 inline-flex flex-wrap items-center gap-2 rounded-lg bg-zinc-100 px-3 py-1.5 text-sm dark:bg-white/10">
                <span class="text-zinc-500">{{ __('Where:') }}</span> <span class="font-medium">{{ \App\Support\Help\HelpLibrary::brand($page->menu) }}</span>
            </p>
        @endif

        <div class="hz-prose mt-8">{!! $page->html() !!}</div>

        <div class="mt-14 grid gap-3 border-t border-zinc-950/[0.07] pt-6 sm:grid-cols-2 dark:border-white/[0.07]">
            <div>
                @if ($previous)
                    <a href="/help/{{ $previous->slug }}" class="block rounded-xl p-4 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">
                        <div class="text-xs text-zinc-500">← {{ __('Previous') }}</div>
                        <div class="mt-1 font-medium">{{ \App\Support\Help\HelpLibrary::brand($previous->title) }}</div>
                    </a>
                @endif
            </div>
            <div>
                @if ($next)
                    <a href="/help/{{ $next->slug }}" class="block rounded-xl p-4 text-end ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">
                        <div class="text-xs text-zinc-500">{{ __('Next') }} →</div>
                        <div class="mt-1 font-medium">{{ \App\Support\Help\HelpLibrary::brand($next->title) }}</div>
                    </a>
                @endif
            </div>
        </div>
    </article>

    @if (count($page->contents()) > 1)
        <aside class="hidden xl:block">
            <div class="sticky top-24 text-sm">
                <div class="mb-2 font-semibold">{{ __('On this page') }}</div>
                @foreach ($page->contents() as $c)
                    <a href="#{{ $c['anchor'] }}" class="block py-1 text-zinc-600 hover:text-brand dark:text-zinc-400 dark:hover:text-emerald-300">{{ $c['heading'] }}</a>
                @endforeach
            </div>
        </aside>
    @endif
</div>
@endsection
