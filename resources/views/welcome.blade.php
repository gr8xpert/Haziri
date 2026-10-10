@php($domain = config('hazri.central_domain'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Haziri is attendance software for Pakistani businesses: ZKTeco machines, mobile punch, kiosk, leave and month-end reports.">
    <meta name="theme-color" content="#047857">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="/hazri-icon.svg" type="image/svg+xml">
    <title>Haziri | Attendance and leave for growing teams</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-zinc-50">

    <header class="sticky top-0 z-30 border-b border-zinc-950/[0.06] bg-white/80 backdrop-blur-lg dark:border-white/[0.06] dark:bg-zinc-950/80">
        <nav class="mx-auto flex h-16 max-w-6xl items-center gap-8 px-4 sm:px-6">
            <a href="/" class="flex items-center gap-2.5 font-semibold tracking-tight">
                <img src="/hazri-icon.svg" alt="" class="size-8 rounded-lg"> Haziri
            </a>
            <div class="hidden items-center gap-6 text-sm text-zinc-600 md:flex dark:text-zinc-400">
                <a href="#features" class="transition hover:text-zinc-950 dark:hover:text-white">Features</a>
                <a href="#devices" class="transition hover:text-zinc-950 dark:hover:text-white">Devices</a>
            </div>
            <a href="#signin" class="ms-auto inline-flex items-center gap-1.5 rounded-lg bg-zinc-950 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 active:scale-[0.98] dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200">
                Sign in
            </a>
        </nav>
    </header>

    <main>
        {{-- Hero --}}
        <section class="relative isolate overflow-hidden">
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-[radial-gradient(55%_60%_at_80%_20%,rgb(4_120_87/0.10),transparent)] dark:bg-[radial-gradient(55%_60%_at_80%_20%,rgb(52_211_153/0.10),transparent)]"></div>
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-14 pb-16 sm:px-6 md:pt-20 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-10 lg:pb-24">
                <div>
                    <h1 class="text-4xl leading-[1.05] font-semibold tracking-tight text-balance md:text-5xl lg:text-[3.5rem]">
                        Attendance your HR team can trust at month end.
                    </h1>
                    <p class="mt-5 max-w-[38ch] text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                        Connect your ZKTeco machines, let staff punch from their phones, and close every month with clean reports.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a href="#signin" class="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-brand-dark active:scale-[0.98] dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400">
                            Sign in to your company
                            @svg('heroicon-m-arrow-right', 'size-4 rtl:rotate-180')
                        </a>
                        @if (filled(config('hazri.demo.tenant')))
                            {{-- The public demo company: a full working company anyone can log in to (reset nightly). --}}
                            <a href="{{ request()->getScheme() }}://{{ config('hazri.demo.tenant') }}.{{ config('hazri.central_domain') }}{{ in_array(request()->getPort(), [80, 443], true) ? '' : ':' . request()->getPort() }}/admin/login"
                               class="inline-flex items-center gap-2 rounded-xl px-4 py-3 font-medium text-zinc-700 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-white/15 dark:hover:bg-white/5">
                                @svg('heroicon-m-play-circle', 'size-5 text-brand dark:text-emerald-400')
                                Try the live demo
                            </a>
                        @else
                            <a href="#features" class="inline-flex items-center gap-2 rounded-xl px-4 py-3 font-medium text-zinc-700 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-white/15 dark:hover:bg-white/5">
                                See what it does
                            </a>
                        @endif
                    </div>
                </div>
                <figure class="relative">
                    <div class="overflow-hidden rounded-2xl bg-zinc-100 shadow-[0_24px_60px_-20px_rgb(15_23_42/0.30)] ring-1 ring-zinc-950/10 lg:-me-24 dark:bg-zinc-900 dark:ring-white/10">
                        <div class="flex h-8 items-center gap-1.5 border-b border-zinc-950/[0.06] bg-zinc-50 px-3 dark:border-white/[0.06] dark:bg-zinc-900" aria-hidden="true">
                            <span class="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-700"></span><span class="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-700"></span><span class="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
                        </div>
                        <img src="/images/marketing/hazri-dashboard.jpg" width="1440" height="900" alt="The Haziri dashboard showing who is present, late and absent today" class="block w-full" fetchpriority="high">
                    </div>
                </figure>
            </div>
        </section>

        {{-- Features: bento --}}
        <section id="features" class="scroll-mt-20 border-t border-zinc-950/[0.06] bg-zinc-50 py-20 md:py-24 dark:border-white/[0.06] dark:bg-zinc-900/40">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <h2 class="max-w-2xl text-3xl font-semibold tracking-tight text-balance md:text-4xl">Every way your people clock in, in one register.</h2>
                <p class="mt-4 max-w-[60ch] text-zinc-600 dark:text-zinc-400">Machine punches, phone punches and kiosk punches land in the same daily sheet, with late, half-day and overtime worked out for you.</p>

                <div class="mt-12 grid gap-4 md:grid-cols-6">
                    <article class="relative overflow-hidden rounded-2xl bg-white p-6 ring-1 ring-zinc-950/[0.07] md:col-span-4 md:row-span-2 dark:bg-zinc-900 dark:ring-white/10">
                        <h3 class="text-lg font-semibold">A self-service app for every employee</h3>
                        <p class="mt-2 max-w-[44ch] text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Punch in with location and a selfie, check the month, apply for leave and fix a missed punch. It installs from the browser, no app store needed.</p>
                        <div class="mt-6 flex justify-center md:justify-end">
                            <img src="/images/marketing/hazri-app.jpg" width="390" height="780" loading="lazy" alt="The Haziri employee app on a phone, showing today's in and out times"
                                 class="w-56 rounded-[2rem] shadow-[0_20px_50px_-20px_rgb(15_23_42/0.35)] ring-8 ring-zinc-900 md:-mb-24 md:w-64 dark:ring-zinc-800">
                        </div>
                    </article>
                    <article class="rounded-2xl bg-brand p-6 text-white md:col-span-2 dark:bg-emerald-600">
                        @svg('heroicon-o-document-chart-bar', 'size-7 text-emerald-200')
                        <h3 class="mt-4 text-lg font-semibold">Month-end in minutes</h3>
                        <p class="mt-2 text-sm leading-relaxed text-emerald-50/85">Over twenty reports, from the monthly sheet to payroll input, in Excel or PDF. Lock the month when it is final.</p>
                    </article>
                    <article class="rounded-2xl bg-white p-6 ring-1 ring-zinc-950/[0.07] md:col-span-2 dark:bg-zinc-900 dark:ring-white/10">
                        @svg('heroicon-o-calendar-days', 'size-7 text-brand dark:text-emerald-400')
                        <h3 class="mt-4 text-lg font-semibold">Leave and corrections</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Requests, approvals and balances, with your own leave year and pay cycle.</p>
                    </article>
                    <article class="relative overflow-hidden rounded-2xl bg-zinc-900 text-white md:col-span-3 dark:ring-1 dark:ring-white/10">
                        <img src="/images/marketing/hazri-kiosk.jpg" width="1280" height="800" loading="lazy" alt="The Haziri reception kiosk with a PIN keypad" class="aspect-[16/10] w-full object-cover opacity-90">
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-zinc-950/90 via-zinc-950/60 to-transparent p-6 pt-16">
                            <h3 class="text-lg font-semibold">A kiosk on any tablet</h3>
                            <p class="mt-1 text-sm text-zinc-300">PIN or QR badge with a photo. Keeps working when the internet drops.</p>
                        </div>
                    </article>
                    <article class="flex flex-col justify-between rounded-2xl bg-white p-6 ring-1 ring-zinc-950/[0.07] md:col-span-3 dark:bg-zinc-900 dark:ring-white/10">
                        <div>
                            @svg('heroicon-o-chat-bubble-left-right', 'size-7 text-brand dark:text-emerald-400')
                            <h3 class="mt-4 text-lg font-semibold">WhatsApp and SMS alerts</h3>
                            <p class="mt-2 max-w-[44ch] text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Tell managers who is absent, remind staff about a missing punch and confirm leave decisions on the channel people actually read.</p>
                        </div>
                        <div class="mt-6 flex items-center gap-3 rounded-xl bg-zinc-50 p-3 text-sm ring-1 ring-zinc-950/5 dark:bg-white/5 dark:ring-white/10">
                            @svg('heroicon-m-sparkles', 'size-5 shrink-0 text-brand dark:text-emerald-400')
                            <span class="text-zinc-700 dark:text-zinc-300">Ask questions in plain English, like "who was late more than three times in August?"</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        {{-- Devices --}}
        <section id="devices" class="scroll-mt-20 py-20 md:py-24">
            <div class="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16">
                <div>
                    <h2 class="text-3xl font-semibold tracking-tight text-balance md:text-4xl">Keep the fingerprint machines you already have.</h2>
                    <p class="mt-4 max-w-[52ch] text-zinc-600 dark:text-zinc-400">Haziri reads ZKTeco devices three ways, so older and newer machines both work. Unknown PINs wait in a queue until you map them, so no punch is lost.</p>
                </div>
                <ol class="grid gap-3">
                    @foreach ([
                        ['heroicon-o-cloud-arrow-up', 'Cloud push', 'Newer machines send punches straight to Haziri over the internet.'],
                        ['heroicon-o-server-stack', 'Bridge agent', 'A small program on an office PC collects punches from machines on the local network.'],
                        ['heroicon-o-arrow-down-tray', 'USB import', 'Export the attlog file from any machine and upload it. Duplicates are skipped.'],
                    ] as [$icon, $title, $body])
                        <li class="flex gap-4 rounded-2xl p-5 ring-1 ring-zinc-950/[0.07] dark:ring-white/10">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-brand dark:bg-emerald-500/10 dark:text-emerald-400">@svg($icon, 'size-5')</span>
                            <div>
                                <h3 class="font-semibold">{{ $title }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $body }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Sign in --}}
        <section id="signin" class="scroll-mt-20 px-4 pb-24 sm:px-6">
            <div class="mx-auto max-w-6xl overflow-hidden rounded-3xl bg-brand-deep px-5 py-12 text-white sm:px-12 md:py-16">
                <div class="grid items-center gap-10 lg:grid-cols-2">
                    <div class="min-w-0">
                        <h2 class="text-3xl font-semibold tracking-tight text-balance">Sign in to your company</h2>
                        <p class="mt-3 max-w-[46ch] text-emerald-100/75">Enter the address your company uses on Haziri. Admins land in the dashboard, employees can open the app at the same address followed by /me.</p>
                    </div>
                    <form x-data="{ slug: '' }"
                          @submit.prevent="if (slug.trim()) window.location.href = `${location.protocol}//${slug.trim().toLowerCase()}.{{ $domain }}${location.port ? ':' + location.port : ''}/admin`"
                          class="flex min-w-0 flex-col gap-3 sm:flex-row">
                        <label for="company-slug" class="sr-only">Company address</label>
                        <div class="flex min-w-0 flex-1 items-center rounded-xl bg-white/10 ring-1 ring-white/20 transition focus-within:ring-2 focus-within:ring-emerald-300">
                            <input id="company-slug" x-model="slug" required autocomplete="organization" autocapitalize="none" spellcheck="false" pattern="[A-Za-z0-9-]+"
                                   placeholder="yourcompany" class="min-w-0 flex-1 bg-transparent py-3 ps-4 text-base text-white placeholder:text-white/40 focus:outline-none">
                            <span class="max-w-[50%] shrink-0 truncate pe-4 text-white/60">.{{ $domain }}</span>
                        </div>
                        <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 font-semibold text-brand-deep transition hover:bg-emerald-50 active:scale-[0.98]">
                            Continue @svg('heroicon-m-arrow-right', 'size-4 rtl:rotate-180')
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-950/[0.06] dark:border-white/[0.06]">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-zinc-500 sm:px-6 dark:text-zinc-400">
            <span class="flex items-center gap-2"><img src="/hazri-icon.svg" alt="" class="size-5 rounded"> Haziri</span>
            <span>&copy; {{ date('Y') }} Haziri</span>
        </div>
    </footer>
</body>
</html>
