@extends('help.layout')
@section('search-placement', 'hero')

@php($brand = \App\Support\Brand::name())
{{-- Every topic by slug, so the hand-picked links below only render when the topic exists. --}}
@php($topics = $groups->flatten(1)->keyBy('slug'))
@php($link = fn (string $slug): ?string => $topics->has($slug) ? '/help/' . $slug : null)

{{-- Each group in one friendly line, with an icon. Groups without an entry here still render, with the defaults. --}}
@php($groupMeta = [
    'Getting started' => ['heroicon-o-rocket-launch', __('What :app is, your first login and the order to set things up.', ['app' => $brand])],
    'Company setup' => ['heroicon-o-building-office-2', __('Your rules, logins, branches, branding and billing.')],
    'People' => ['heroicon-o-users', __('Add staff, import them from Excel, record faces, send notices.')],
    'Shifts & holidays' => ['heroicon-o-clock', __('Working hours, rosters, holidays and days to excuse.')],
    'Attendance' => ['heroicon-o-clipboard-document-check', __('Punches, approvals, corrections and locking the month.')],
    'Leave' => ['heroicon-o-calendar-days', __('Leave types, requests, balances and who is off today.')],
    'Devices' => ['heroicon-o-finger-print', __('Fingerprint machines, kiosk tablets, USB files and phone punching.')],
    'Reports' => ['heroicon-o-document-chart-bar', __('Daily, monthly, leave and payroll reports in Excel or PDF.')],
    'Payroll' => ['heroicon-o-banknotes', __('Salaries, advances, payroll runs and final settlements.')],
    'Insights' => ['heroicon-o-sparkles', __('Analytics, anomalies and the AI assistant.')],
    'System' => ['heroicon-o-cog-6-tooth', __('WhatsApp and SMS, the API and the audit log.')],
    'For staff' => ['heroicon-o-device-phone-mobile', __('For employees: the phone app and the kiosk.')],
])

@section('content')
{{-- Hero: one question, one search box --}}
<section class="relative isolate overflow-hidden border-b border-zinc-950/[0.07] bg-zinc-50 dark:border-white/[0.07] dark:bg-zinc-900/40">
    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-[radial-gradient(60%_80%_at_50%_0%,rgb(4_120_87/0.10),transparent)] dark:bg-[radial-gradient(60%_80%_at_50%_0%,rgb(52_211_153/0.10),transparent)]"></div>
    <div class="mx-auto max-w-3xl px-4 py-14 text-center sm:px-6 md:py-20">
        <h1 class="text-4xl font-semibold tracking-tight text-balance md:text-5xl">{{ __('What do you need help with?') }}</h1>
        <p class="mx-auto mt-4 max-w-[52ch] text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
            {{ __('Every page of :app explained in plain words, with pictures. Type what you want to do.', ['app' => $brand]) }}
        </p>

        <div class="relative mx-auto mt-8 max-w-2xl text-start" data-hz-search>
            <label for="hz-q" class="sr-only">{{ __('Search the help') }}</label>
            <div class="flex items-center gap-3 rounded-2xl bg-white px-4 shadow-[0_8px_30px_-12px_rgb(15_23_42/0.25)] ring-1 ring-zinc-950/10 transition focus-within:ring-2 focus-within:ring-brand dark:bg-zinc-900 dark:shadow-none dark:ring-white/15 dark:focus-within:ring-emerald-400">
                @svg('heroicon-m-magnifying-glass', 'size-5 shrink-0 text-zinc-400')
                <input id="hz-q" type="search" autocomplete="off" autofocus placeholder="{{ __('Try: add an employee, apply for leave, machine offline') }}"
                       class="min-w-0 flex-1 bg-transparent py-4 text-base outline-none placeholder:text-zinc-400 md:text-lg dark:placeholder:text-zinc-500">
                <kbd class="hidden rounded-md bg-zinc-100 px-1.5 py-0.5 font-sans text-xs text-zinc-500 sm:inline dark:bg-white/10" aria-hidden="true">/</kbd>
            </div>
            <div data-hz-results class="absolute inset-x-0 top-full z-20 mt-2 hidden max-h-[60vh] overflow-y-auto rounded-xl bg-white p-1.5 shadow-xl ring-1 ring-zinc-950/10 dark:bg-zinc-900 dark:ring-white/10"></div>
        </div>

        <p class="mt-5 flex flex-wrap items-center justify-center gap-2 text-sm">
            <span class="text-zinc-500">{{ __('Popular:') }}</span>
            @foreach ([['employees', __('Add an employee')], ['leave-requests', __('Apply for leave')], ['corrections', __('Fix a missed punch')], ['zkteco-machines', __('Machine is offline')], ['month-lock', __('Lock the month')]] as [$slug, $label])
                @if ($href = $link($slug))
                    <a href="{{ $href }}" class="rounded-full bg-white px-3 py-1 text-zinc-700 ring-1 ring-zinc-950/10 transition hover:text-brand dark:bg-zinc-900 dark:text-zinc-300 dark:ring-white/15 dark:hover:text-emerald-300">{{ $label }}</a>
                @endif
            @endforeach
        </p>
    </div>
</section>

<div class="mx-auto max-w-7xl px-4 sm:px-6">
    {{-- Who are you? Three doors into the guide. --}}
    <section class="py-14 md:py-16" aria-labelledby="who">
        <h2 id="who" class="text-2xl font-semibold tracking-tight md:text-3xl">{{ __('Start with who you are') }}</h2>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('Each door shows only the pages that person needs.') }}</p>

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach ([
                [
                    'heroicon-o-device-phone-mobile', __('I am an employee'), __('I punch in and out, and I want to see my attendance, leave and payslip.'),
                    [['staff-app', __('Use the phone app')], ['using-the-kiosk', __('Punch on the kiosk tablet')], ['phone-punching', __('Punch from my phone')], ['leave-requests', __('Ask for leave')], ['corrections', __('I forgot to punch')]],
                ],
                [
                    'heroicon-o-clipboard-document-check', __('I run attendance (HR or manager)'), __('I check the daily sheet, approve leave, fix mistakes and prepare the month.'),
                    [['how-attendance-works', __('How a day is worked out')], ['punch-approvals', __('Approve phone punches')], ['leave-requests', __('Approve leave')], ['manual-entry', __('Enter times by hand')], ['month-lock', __('Lock the month')], ['monthly-attendance-report', __('The monthly report')]],
                ],
                [
                    'heroicon-o-building-office-2', __('I own or set up the company'), __('I set the rules, add machines and logins, and look after billing.'),
                    [['first-setup', __('Set up in the right order')], ['company-settings', __('Company rules')], ['shifts', __('Working hours and shifts')], ['devices-overview', __('Connect machines and tablets')], ['users-and-roles', __('Logins for HR and managers')], ['billing', __('Billing and payment')]],
                ],
            ] as [$icon, $title, $body, $links])
                <article class="flex flex-col rounded-2xl bg-white p-6 ring-1 ring-zinc-950/[0.07] dark:bg-zinc-900 dark:ring-white/10">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-brand dark:bg-emerald-500/10 dark:text-emerald-400">@svg($icon, 'size-6')</span>
                    <h3 class="mt-4 text-lg font-semibold">{{ $title }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $body }}</p>
                    <ul class="mt-4 space-y-1 border-t border-zinc-950/[0.07] pt-4 text-sm dark:border-white/[0.07]">
                        @foreach ($links as [$slug, $label])
                            @if ($href = $link($slug))
                                <li><a href="{{ $href }}" class="group flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 -mx-2 transition hover:bg-zinc-50 dark:hover:bg-white/5"><span>{{ $label }}</span>@svg('heroicon-m-chevron-right', 'size-4 shrink-0 text-zinc-400 transition group-hover:text-brand rtl:rotate-180 dark:group-hover:text-emerald-300')</a></li>
                            @endif
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Common tasks, in the reader's words --}}
    <section class="border-t border-zinc-950/[0.07] py-14 md:py-16 dark:border-white/[0.07]" aria-labelledby="tasks">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:gap-14">
            <div>
                <h2 id="tasks" class="text-2xl font-semibold tracking-tight md:text-3xl">{{ __('How do I…') }}</h2>
                <p class="mt-2 max-w-[44ch] text-zinc-600 dark:text-zinc-400">{{ __('The jobs people do most often. Each link opens the page with the steps and a picture.') }}</p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <a href="/help/troubleshooting" class="flex items-start gap-3 rounded-2xl bg-brand p-5 text-white transition hover:bg-brand-dark dark:bg-emerald-600 dark:hover:bg-emerald-500">
                        @svg('heroicon-o-wrench-screwdriver', 'size-6 shrink-0 text-emerald-200')
                        <span><span class="block font-semibold">{{ __('Something is not working') }}</span><span class="mt-1 block text-sm text-emerald-50/85">{{ __('Common problems and the fix for each, from every page.') }}</span></span>
                    </a>
                    <a href="/help/quick" class="flex items-start gap-3 rounded-2xl p-5 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:ring-white/10 dark:hover:bg-white/5">
                        @svg('heroicon-o-list-bullet', 'size-6 shrink-0 text-brand dark:text-emerald-400')
                        <span><span class="block font-semibold">{{ __('Quick reference') }}</span><span class="mt-1 block text-sm text-zinc-600 dark:text-zinc-400">{{ __('Every page in a few lines, on one screen.') }}</span></span>
                    </a>
                </div>
            </div>
            <ol class="grid gap-x-6 sm:grid-cols-2">
                @foreach ([
                    ['employees', __('add a new employee?')],
                    ['employee-import', __('bring my staff list in from Excel?')],
                    ['zkteco-machines', __('connect our fingerprint machine?')],
                    ['unmapped-pins', __('match a machine PIN to a person?')],
                    ['phone-punching', __('let staff punch from their phones?')],
                    ['kiosk', __('set up a kiosk tablet at the door?')],
                    ['shifts', __('change working hours or grace time?')],
                    ['holidays', __('add a public holiday?')],
                    ['corrections', __('fix a missed or wrong punch?')],
                    ['leave-requests', __('approve or reject leave?')],
                    ['month-lock', __('close the month for salaries?')],
                    ['monthly-attendance-report', __('download the monthly attendance sheet?')],
                    ['users-and-roles', __('give HR or a manager a login?')],
                    ['notifications', __('send WhatsApp or SMS alerts?')],
                ] as [$slug, $label])
                    @if ($href = $link($slug))
                        <li class="border-b border-zinc-950/[0.07] dark:border-white/[0.07]">
                            <a href="{{ $href }}" class="group flex items-center justify-between gap-3 py-3 transition hover:text-brand dark:hover:text-emerald-300">
                                <span><span class="text-zinc-400">{{ __('How do I') }}</span> {{ $label }}</span>
                                @svg('heroicon-m-arrow-right', 'size-4 shrink-0 text-zinc-300 transition group-hover:text-brand rtl:rotate-180 dark:text-zinc-600 dark:group-hover:text-emerald-300')
                            </a>
                        </li>
                    @endif
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Every topic, by group --}}
    <section class="border-t border-zinc-950/[0.07] py-14 md:py-16 dark:border-white/[0.07]" aria-labelledby="all">
        <h2 id="all" class="text-2xl font-semibold tracking-tight md:text-3xl">{{ __('All topics') }}</h2>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">{{ __('The whole guide, in the same order as the menu in :app.', ['app' => $brand]) }}</p>

        <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($groups as $group => $pages)
                @php([$icon, $line] = $groupMeta[$group] ?? ['heroicon-o-book-open', ''])
                <section class="rounded-2xl p-5 ring-1 ring-zinc-950/[0.07] dark:ring-white/10" aria-labelledby="group-{{ \Illuminate\Support\Str::slug($group) }}">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-brand dark:bg-emerald-500/10 dark:text-emerald-400">@svg($icon, 'size-5')</span>
                        <h3 id="group-{{ \Illuminate\Support\Str::slug($group) }}" class="font-semibold">{{ $group }}</h3>
                        <span class="ms-auto text-xs text-zinc-400 tabular-nums">{{ $pages->count() }}</span>
                    </div>
                    @if ($line)
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $line }}</p>
                    @endif
                    <ul class="mt-3 grid gap-x-4 text-sm sm:grid-cols-2 md:grid-cols-1">
                        @foreach ($pages as $p)
                            <li><a href="/help/{{ $p->slug }}" title="{{ \App\Support\Help\HelpLibrary::brand($p->summary) }}" class="block border-t border-zinc-950/[0.05] py-2 transition hover:text-brand dark:border-white/[0.06] dark:hover:text-emerald-300">{{ \App\Support\Help\HelpLibrary::brand($p->title) }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </section>
</div>
@endsection
