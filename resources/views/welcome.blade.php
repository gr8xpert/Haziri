@php($domain = config('hazri.central_domain'))
@php($demoUrl = filled(config('hazri.demo.tenant')) ? request()->getScheme() . '://' . config('hazri.demo.tenant') . '.' . $domain . (in_array(request()->getPort(), [80, 443], true) ? '' : ':' . request()->getPort()) . '/admin/login' : null)
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

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

    <header class="sticky top-0 z-30 border-b border-zinc-950/[0.06] bg-white/80 backdrop-blur-lg dark:border-white/[0.06] dark:bg-zinc-950/80">
        <nav class="mx-auto flex h-16 max-w-6xl items-center gap-8 px-4 sm:px-6" aria-label="Main">
            <a href="/" class="flex items-center gap-2.5 font-semibold tracking-tight">
                <img src="/hazri-icon.svg" alt="" class="size-8 rounded-lg"> Haziri
            </a>
            <div class="hidden items-center gap-6 text-sm text-zinc-600 md:flex dark:text-zinc-400">
                <a href="#how" class="transition hover:text-zinc-950 dark:hover:text-white">How it works</a>
                <a href="#features" class="transition hover:text-zinc-950 dark:hover:text-white">Features</a>
                <a href="#devices" class="transition hover:text-zinc-950 dark:hover:text-white">Devices</a>
                <a href="#faq" class="transition hover:text-zinc-950 dark:hover:text-white">FAQ</a>
                <a href="/help" class="transition hover:text-zinc-950 dark:hover:text-white">Help</a>
            </div>
            <a href="#signin" class="ms-auto inline-flex items-center gap-1.5 rounded-lg bg-zinc-950 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 active:scale-[0.98] dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200">
                Sign in
            </a>
        </nav>
    </header>

    <main id="main">
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
                        @if ($demoUrl)
                            {{-- The public demo company: a full working company anyone can log in to (reset nightly). --}}
                            <a href="{{ $demoUrl }}" class="inline-flex items-center gap-2 rounded-xl px-4 py-3 font-medium text-zinc-700 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-white/15 dark:hover:bg-white/5">
                                @svg('heroicon-m-play-circle', 'size-5 text-brand dark:text-emerald-400')
                                Try the live demo
                            </a>
                        @else
                            <a href="#how" class="inline-flex items-center gap-2 rounded-xl px-4 py-3 font-medium text-zinc-700 ring-1 ring-zinc-950/10 transition hover:bg-zinc-50 dark:text-zinc-200 dark:ring-white/15 dark:hover:bg-white/5">
                                See how it works
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

        {{-- How it works: three plain steps --}}
        <section id="how" class="scroll-mt-20 border-t border-zinc-950/[0.06] py-20 md:py-24 dark:border-white/[0.06]">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <h2 class="max-w-2xl text-3xl font-semibold tracking-tight text-balance md:text-4xl">How it works, in three steps.</h2>
                <p class="mt-4 max-w-[60ch] text-zinc-600 dark:text-zinc-400">You do not type anyone's attendance. Staff punch, Haziri works out each day, and you close the month.</p>

                <ol class="mt-12 grid gap-10 md:grid-cols-3 md:gap-8">
                    @foreach ([
                        ['heroicon-o-finger-print', 'Staff punch in and out', 'On the fingerprint machine, on a kiosk tablet at the door, or from their own phone with a selfie and location.'],
                        ['heroicon-o-calculator', 'Haziri works out the day', 'Present, late, half day or absent, using the shift, grace time, approved leave and holidays. Fix a missed punch and the day is worked out again.'],
                        ['heroicon-o-lock-closed', 'You close the month', 'Check the sheet, lock the month so nothing changes, then download the reports or run payroll.'],
                    ] as $i => [$icon, $title, $body])
                        <li class="relative border-t-2 border-brand/30 pt-6 dark:border-emerald-400/30">
                            <div class="flex items-center gap-3">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-brand dark:bg-emerald-500/10 dark:text-emerald-400">@svg($icon, 'size-6')</span>
                                <span class="text-sm font-semibold text-brand tabular-nums dark:text-emerald-400">Step {{ $i + 1 }}</span>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold">{{ $title }}</h3>
                            <p class="mt-2 max-w-[40ch] leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $body }}</p>
                        </li>
                    @endforeach
                </ol>
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

                {{-- Everything included, three to a row. One line each; the user guide (/help) has the detail. --}}
                <div id="all-features" class="mt-20 scroll-mt-20 md:mt-24">
                    <h3 class="text-2xl font-semibold tracking-tight text-balance md:text-3xl">Everything that is included</h3>
                    <p class="mt-3 max-w-[60ch] text-zinc-600 dark:text-zinc-400">Every part of Haziri in one list. Some parts depend on the plan you choose.</p>
                    <ul class="mt-10 grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                        @foreach ([
                            ['heroicon-o-finger-print', 'Fingerprint and face machines', 'Connect ZKTeco machines over the internet, through a small sync program on an office PC, or by uploading the USB file.', '/help/devices-overview'],
                            ['heroicon-o-device-tablet', 'Kiosk tablet', 'Any tablet becomes an attendance point. Staff punch with a PIN, their QR badge or their face, with a photo. Works offline.', '/help/kiosk'],
                            ['heroicon-o-map-pin', 'Phone punching', 'Staff punch from their own phone with location and a selfie. A punch from outside the office waits for approval.', '/help/phone-punching'],
                            ['heroicon-o-device-phone-mobile', 'Staff app', 'Each employee sees their own days, leave balance, monthly report and payslip. Opens from the browser, no app store.', '/help/staff-app'],
                            ['heroicon-o-calculator', 'Automatic daily status', 'Present, late, half day, absent and overtime worked out from the shift rules. Nobody types a status by hand.', '/help/how-attendance-works'],
                            ['heroicon-o-clock', 'Shifts and rosters', 'Fixed shifts or a weekly roster, shift swaps, and timing changes for Ramadan or Friday prayers.', '/help/shifts'],
                            ['heroicon-o-calendar-days', 'Leave', 'Leave types and yearly quotas, requests from the phone, manager then HR approval, and a calendar of who is off.', '/help/leave-requests'],
                            ['heroicon-o-pencil-square', 'Corrections', 'Missed punch, work from home or official duty: staff ask, HR approves, and the day is worked out again.', '/help/corrections'],
                            ['heroicon-o-sun', 'Holidays and exemptions', 'Pakistan\'s public holidays loaded for you, plus your own. Excuse a late day for everyone when rain or a strike hits.', '/help/holidays'],
                            ['heroicon-o-lock-closed', 'Month lock', 'Review the month, then lock it so the numbers are final. Only an owner or admin can unlock, and must give a reason.', '/help/month-lock'],
                            ['heroicon-o-document-chart-bar', 'Reports', 'Daily muster roll, monthly sheet, late and absent registers, leave and payroll reports, all in Excel or PDF.', '/help/reports-overview'],
                            ['heroicon-o-banknotes', 'Payroll', 'Salary structures, advances and bonuses, the salary sheet, a bank transfer file, payslips and final settlements.', '/help/payroll-overview'],
                            ['heroicon-o-chat-bubble-left-right', 'WhatsApp and SMS alerts', 'Absent and late alerts to managers, forgot-to-punch reminders, leave decisions and payslips on WhatsApp or SMS.', '/help/notifications'],
                            ['heroicon-o-megaphone', 'Announcements', 'Send a notice to all staff or one branch. Staff tap "I have read this" and you see who has.', '/help/announcements'],
                            ['heroicon-o-identification', 'Visitors', 'Sign visitors in and out at reception with a photo and a printed badge, and see who is still inside.', '/help/visitors'],
                            ['heroicon-o-sparkles', 'Analytics and AI assistant', 'Punctuality scores, trends and odd patterns, and answers to questions asked in plain English.', '/help/analytics'],
                            ['heroicon-o-user-group', 'Users and roles', 'Owner, admin, HR, manager, branch manager and receptionist. Each sees only the part of Haziri they need.', '/help/users-and-roles'],
                            ['heroicon-o-building-office-2', 'Branches and departments', 'Several branches, departments and designations in one company. A branch manager sees only their branch.', '/help/organisation'],
                            ['heroicon-o-table-cells', 'Excel import', 'Bring your staff list in from Excel or CSV with a template and a preview before anything is saved.', '/help/employee-import'],
                            ['heroicon-o-paint-brush', 'Your own branding', 'Your logo, colours and app name for your staff, and Haziri on your own web address.', '/help/branding'],
                            ['heroicon-o-shield-check', 'Audit log and API', 'Every change is recorded with who made it and when. Connect other systems through the API and webhooks.', '/help/audit-log'],
                        ] as [$icon, $title, $body, $href])
                            <li class="rounded-2xl bg-white p-5 ring-1 ring-zinc-950/[0.07] transition hover:ring-brand/40 dark:bg-zinc-900 dark:ring-white/10 dark:hover:ring-emerald-400/40">
                                <a href="{{ $href }}" class="group block">
                                    <span class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-brand dark:bg-emerald-500/10 dark:text-emerald-400">@svg($icon, 'size-5')</span>
                                    <span class="mt-4 block font-semibold group-hover:text-brand dark:group-hover:text-emerald-300">{{ $title }}</span>
                                    <span class="mt-1.5 block text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $body }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
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

        {{-- FAQ: the questions people ask before and after they start. Answers match the user guide (resources/docs). --}}
        <section id="faq" class="scroll-mt-20 border-t border-zinc-950/[0.06] bg-zinc-50 py-20 md:py-24 dark:border-white/[0.06] dark:bg-zinc-900/40">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <h2 class="text-3xl font-semibold tracking-tight text-balance md:text-4xl">Questions people ask us</h2>
                <p class="mt-4 text-zinc-600 dark:text-zinc-400">Short answers in plain words. The full guide has the details for every page.</p>

                <div class="mt-10 divide-y divide-zinc-950/[0.07] rounded-2xl bg-white ring-1 ring-zinc-950/[0.07] dark:divide-white/[0.07] dark:bg-zinc-900 dark:ring-white/10">
                    @foreach ([
                        ['Do we have to replace our fingerprint machines?', 'No. Haziri reads ZKTeco machines three ways: newer machines send punches over the internet, a small sync program on an office PC collects from older machines, and the attendance file from any machine can be uploaded from a USB stick. A PIN that no employee has yet waits in a queue until you match it, so no punch is lost.', '/help/devices-overview'],
                        ['Can staff mark attendance from their phones?', 'Yes, if you allow it. The staff app saves the location and a selfie with every punch. A punch made inside the office area counts straight away. One made from outside is kept but waits for approval. Field and remote staff can be freed from the office check.', '/help/phone-punching'],
                        ['Do employees need to install an app?', 'No. The staff app is a web page at your company address followed by /me. Staff add it to the phone\'s home screen and it opens like an app. They sign in with their mobile number and a 6-digit code, or with email and password.', '/help/staff-app'],
                        ['What happens when the internet drops?', 'The kiosk tablet keeps the punches and sends them when the internet is back, for up to 24 hours. Fingerprint machines keep their own log and send it later. As a backup you can always upload the machine\'s file from a USB stick.', '/help/kiosk'],
                        ['How is a day marked Present, Late or Absent?', 'Haziri matches each day\'s punches to the employee\'s shift. Late means the first punch came after the shift start plus the grace time. A day with no punches becomes Absent once the day is over. Approved leave, holidays and weekly offs come first. Nobody types the status by hand.', '/help/how-attendance-works'],
                        ['How do we get the month ready for salaries?', 'Check the month, fix missing punches with corrections, then lock the month under Attendance. Locked days never change. Download the monthly sheet or the payroll input in Excel or PDF, or run payroll inside Haziri if your plan includes it.', '/help/month-lock'],
                        ['Can employees see their own attendance and payslip?', 'Yes. In the staff app they see each day of the month, how many leave days they have left, their monthly report as a PDF and, once payroll is final, their payslip.', '/help/staff-app'],
                        ['How is Haziri priced?', 'Per active employee, per month. An invoice is made on the 1st of each month and can be paid by bank transfer, JazzCash, Easypaisa or cash. New companies start on a free trial.', '/help/billing'],
                        ['Is our data kept separate from other companies?', 'Yes. Each company has its own address and its own database. Nobody from another company can see your data.', '/help/welcome'],
                    ] as $i => [$question, $answer, $more])
                        <details name="faq" class="group" @if ($i === 0) open @endif>
                            <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-4 text-start font-medium transition hover:bg-zinc-50 sm:px-6 dark:hover:bg-white/5 [&::-webkit-details-marker]:hidden">
                                <span class="flex-1 text-base sm:text-lg">{{ $question }}</span>
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 transition group-open:rotate-45 group-open:bg-brand group-open:text-white dark:bg-white/10 dark:text-zinc-300 dark:group-open:bg-emerald-500 dark:group-open:text-emerald-950" aria-hidden="true">
                                    @svg('heroicon-m-plus', 'size-4')
                                </span>
                            </summary>
                            <div class="px-5 pb-5 sm:px-6">
                                <p class="max-w-[62ch] leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $answer }}</p>
                                <a href="{{ $more }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline dark:text-emerald-300">Read the full page @svg('heroicon-m-arrow-right', 'size-3.5 rtl:rotate-180')</a>
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Help: one search box into the user guide --}}
        <section class="py-16 md:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="grid items-center gap-8 rounded-3xl bg-emerald-50 px-5 py-10 ring-1 ring-brand/10 sm:px-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] dark:bg-emerald-500/[0.07] dark:ring-emerald-400/15">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight text-balance md:text-3xl">Already using Haziri? The guide explains every page.</h2>
                        <p class="mt-3 max-w-[48ch] text-zinc-600 dark:text-zinc-400">Written in plain words, with pictures. Start from what you want to do, not from the menu.</p>
                    </div>
                    <div>
                        <form action="/help" method="get" role="search" class="flex flex-col gap-3 sm:flex-row">
                            <label for="help-q" class="sr-only">Search the help guide</label>
                            <div class="flex min-w-0 flex-1 items-center gap-2 rounded-xl bg-white px-4 ring-1 ring-zinc-950/10 transition focus-within:ring-2 focus-within:ring-brand dark:bg-zinc-900 dark:ring-white/15 dark:focus-within:ring-emerald-400">
                                @svg('heroicon-m-magnifying-glass', 'size-5 shrink-0 text-zinc-400')
                                <input id="help-q" name="q" type="search" placeholder="Try: add an employee, apply for leave, machine offline" class="min-w-0 flex-1 bg-transparent py-3 text-base text-zinc-950 placeholder:text-zinc-400 focus:outline-none dark:text-white dark:placeholder:text-zinc-500">
                            </div>
                            <button class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-5 py-3 font-semibold text-white transition hover:bg-brand-dark active:scale-[0.98] dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400">Search the guide</button>
                        </form>
                        <div class="mt-4 flex flex-wrap gap-2 text-sm">
                            @foreach ([['/help/staff-app', 'I am an employee'], ['/help/first-setup', 'Setting up a new company'], ['/help/troubleshooting', 'Something is not working']] as [$href, $label])
                                <a href="{{ $href }}" class="rounded-full bg-white px-3.5 py-1.5 text-zinc-700 ring-1 ring-zinc-950/10 transition hover:text-brand dark:bg-zinc-900 dark:text-zinc-300 dark:ring-white/15 dark:hover:text-emerald-300">{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
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
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-8">
                <div class="max-w-xs">
                    <span class="flex items-center gap-2 font-semibold"><img src="/hazri-icon.svg" alt="" class="size-6 rounded"> Haziri</span>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">Attendance, leave and payroll for growing teams in Pakistan.</p>
                </div>
                <nav class="flex flex-wrap gap-x-10 gap-y-6 text-sm" aria-label="Footer">
                    <div>
                        <div class="font-semibold">Product</div>
                        <ul class="mt-3 space-y-2 text-zinc-600 dark:text-zinc-400">
                            <li><a href="#how" class="hover:text-zinc-950 dark:hover:text-white">How it works</a></li>
                            <li><a href="#features" class="hover:text-zinc-950 dark:hover:text-white">Features</a></li>
                            <li><a href="#devices" class="hover:text-zinc-950 dark:hover:text-white">Devices</a></li>
                            <li><a href="#faq" class="hover:text-zinc-950 dark:hover:text-white">FAQ</a></li>
                        </ul>
                    </div>
                    <div>
                        <div class="font-semibold">Help</div>
                        <ul class="mt-3 space-y-2 text-zinc-600 dark:text-zinc-400">
                            <li><a href="/help" class="hover:text-zinc-950 dark:hover:text-white">User guide</a></li>
                            <li><a href="/help/staff-app" class="hover:text-zinc-950 dark:hover:text-white">For employees</a></li>
                            <li><a href="/help/troubleshooting" class="hover:text-zinc-950 dark:hover:text-white">Troubleshooting</a></li>
                            <li><a href="#signin" class="hover:text-zinc-950 dark:hover:text-white">Sign in</a></li>
                        </ul>
                    </div>
                </nav>
            </div>
            <div class="mt-10 border-t border-zinc-950/[0.06] pt-6 text-sm text-zinc-500 dark:border-white/[0.06] dark:text-zinc-400">&copy; {{ date('Y') }} Haziri</div>
        </div>
    </footer>
</body>
</html>
