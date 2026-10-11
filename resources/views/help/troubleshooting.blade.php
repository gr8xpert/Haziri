@extends('help.layout')
@section('title', __('Troubleshooting'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('Troubleshooting') }}</h1>
    <p class="mt-3 text-zinc-600 dark:text-zinc-400">{{ __('Common problems from every part of the guide. Click a question to see the answer, or search at the top.') }}</p>

    @foreach ($groups as $group => $pages)
        @php($withProblems = $pages->filter(fn ($p) => $p->problems() !== []))
        @if ($withProblems->isNotEmpty())
            <section class="mt-10">
                <h2 class="text-xl font-semibold">{{ $group }}</h2>
                @foreach ($withProblems as $p)
                    <div class="mt-4">
                        <a href="/help/{{ $p->slug }}" class="text-sm font-medium text-zinc-500 hover:text-brand">{{ \App\Support\Help\HelpLibrary::brand($p->title) }}</a>
                        <div class="mt-2 divide-y divide-zinc-950/[0.07] rounded-xl ring-1 ring-zinc-950/10 dark:divide-white/[0.07] dark:ring-white/10">
                            @foreach ($p->problems() as $problem)
                                <details id="{{ $p->slug }}-{{ $problem['anchor'] }}" class="group px-4 py-3">
                                    <summary class="cursor-pointer list-none font-medium">
                                        <span class="me-2 inline-block text-zinc-400 transition group-open:rotate-90">›</span>{{ \App\Support\Help\HelpLibrary::brand($problem['question']) }}
                                    </summary>
                                    <div class="hz-prose hz-prose-sm mt-2 ps-5">{!! \App\Support\Help\HelpLibrary::render($problem['answer']) !!}</div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    @endforeach
</div>
@endsection
