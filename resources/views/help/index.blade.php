@extends('help.layout')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6">
    <h1 class="text-4xl font-semibold tracking-tight text-balance">{{ __('How to use :app', ['app' => \App\Support\Brand::name()]) }}</h1>
    <p class="mt-4 max-w-2xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
        {{ __('Every page explained in plain words, with pictures. Search at the top, or pick a topic below.') }}
    </p>

    <div class="mt-8 grid gap-4 md:grid-cols-3">
        <a href="/help/{{ $groups->first()?->first()?->slug }}" class="rounded-2xl bg-brand p-6 text-white transition hover:bg-brand-dark">
            <div class="text-lg font-semibold">{{ __('New here? Start here') }} →</div>
            <p class="mt-2 text-sm text-emerald-50">{{ __('What :app does, how to log in and what to set up first.', ['app' => \App\Support\Brand::name()]) }}</p>
        </a>
        <a href="/help/quick" class="rounded-2xl p-6 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">
            <div class="text-lg font-semibold">{{ __('Quick reference') }} →</div>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Every page on one screen: what it is for and where to find it.') }}</p>
        </a>
        <a href="/help/troubleshooting" class="rounded-2xl p-6 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">
            <div class="text-lg font-semibold">{{ __('Troubleshooting') }} →</div>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Stuck? Common problems and how to fix them.') }}</p>
        </a>
    </div>

    <div class="mt-14 grid gap-x-10 gap-y-12 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($groups as $group => $pages)
            <section>
                <h2 class="text-sm font-semibold tracking-wide text-zinc-500 uppercase">{{ $group }}</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($pages as $p)
                        <li>
                            <a href="/help/{{ $p->slug }}" class="font-medium hover:text-brand dark:hover:text-emerald-300">{{ \App\Support\Help\HelpLibrary::brand($p->title) }}</a>
                            <p class="mt-0.5 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ \App\Support\Help\HelpLibrary::brand($p->summary) }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</div>
@endsection
