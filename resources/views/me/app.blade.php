@extends('me.layout')

@section('title', __('My attendance'))

@section('content')
@php
    $tabs = [
        'home' => [__('Today'), 'heroicon-o-home', 'heroicon-s-home'],
        'month' => [__('Month'), 'heroicon-o-calendar-days', 'heroicon-s-calendar-days'],
        'leave' => [__('Leave'), 'heroicon-o-sun', 'heroicon-s-sun'],
        'fix' => [__('Correction'), 'heroicon-o-pencil-square', 'heroicon-s-pencil-square'],
    ];
    $initials = collect(explode(' ', $employee->fullName()))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
@endphp
<div x-data="meApp({ canPunch: @js($canPunch), faceCheck: @js($faceCheck ?? false), leaveTypes: @js($leaveTypes), locale: @js(str_replace('_', '-', app()->getLocale())) })" x-init="load()"
     class="mx-auto min-h-dvh max-w-lg pb-[calc(5rem+env(safe-area-inset-bottom))]">

    <header class="sticky top-0 z-20 border-b border-zinc-950/[0.06] bg-zinc-100/85 pt-[env(safe-area-inset-top)] backdrop-blur-lg dark:border-white/[0.06] dark:bg-zinc-950/85">
        <div class="flex h-14 items-center gap-3 px-4">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-semibold text-white dark:bg-emerald-500 dark:text-emerald-950" aria-hidden="true">{{ mb_strtoupper($initials) }}</span>
            <div class="min-w-0 flex-1 leading-tight">
                <div class="truncate text-[0.9375rem] font-semibold">{{ $employee->fullName() }}</div>
                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $company }}</div>
            </div>
            <form method="POST" action="{{ route('me.logout') }}">
                @csrf
                <button class="flex size-10 items-center justify-center rounded-full text-zinc-500 transition hover:bg-zinc-950/5 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white" aria-label="{{ __('Sign out') }}" title="{{ __('Sign out') }}">
                    @svg('heroicon-o-arrow-right-start-on-rectangle', 'size-5 rtl:rotate-180')
                </button>
            </form>
        </div>
    </header>

    {{-- ================= Today ================= --}}
    <section x-show="tab === 'home'" x-transition.opacity.duration.150ms class="space-y-3 p-4" aria-labelledby="me-today-h">
        <div class="me-card overflow-hidden">
            <div class="flex items-start justify-between gap-3 px-5 pt-5">
                <div>
                    <h2 id="me-today-h" class="text-lg font-semibold tracking-tight" x-text="todayLabel()">{{ __('Today') }}</h2>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400" x-text="statusLine()"></p>
                </div>
                <span x-show="data.today?.late_min" x-cloak class="me-chip bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                    @svg('heroicon-m-clock', 'size-3.5') <span x-text="`{{ __('Late') }} ${data.today?.late_min} min`"></span>
                </span>
            </div>

            <dl class="mt-4 grid grid-cols-2 divide-x divide-zinc-950/[0.06] border-y border-zinc-950/[0.06] dark:divide-white/[0.06] dark:border-white/[0.06] rtl:divide-x-reverse">
                <div class="px-5 py-4">
                    <dt class="flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">@svg('heroicon-m-arrow-down-left', 'size-4 text-emerald-600 dark:text-emerald-400') {{ __('In') }}</dt>
                    <dd class="mt-1 text-3xl font-semibold tracking-tight tabular-nums" :class="!data.today?.in && 'text-zinc-300 dark:text-zinc-600'" x-text="data.today?.in ?? '--:--'">--:--</dd>
                </div>
                <div class="px-5 py-4">
                    <dt class="flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">@svg('heroicon-m-arrow-up-right', 'size-4 text-zinc-400') {{ __('Out') }}</dt>
                    <dd class="mt-1 text-3xl font-semibold tracking-tight tabular-nums" :class="!data.today?.out && 'text-zinc-300 dark:text-zinc-600'" x-text="data.today?.out ?? '--:--'">--:--</dd>
                </div>
            </dl>

            <template x-if="canPunch">
                <div class="p-5">
                    <video x-ref="video" x-show="camera" x-cloak playsinline muted class="mb-4 aspect-[4/3] w-full -scale-x-100 rounded-xl bg-zinc-900 object-cover"></video>
                    <button @click="punch()" :disabled="busy || !loaded"
                            class="me-btn h-14 text-lg"
                            :class="data.next_direction === 'out' && 'bg-zinc-900! hover:bg-zinc-800! dark:bg-white! dark:text-zinc-950! dark:hover:bg-zinc-200!'">
                        <span x-show="!busy" class="inline-flex items-center gap-2">
                            @svg('heroicon-o-finger-print', 'size-6')
                            <span x-text="data.next_direction === 'out' ? '{{ __('Punch out') }}' : '{{ __('Punch in') }}'">{{ __('Punch in') }}</span>
                        </span>
                        <span x-show="busy" x-cloak class="inline-flex items-center gap-2">
                            @svg('heroicon-m-arrow-path', 'size-5 animate-spin')
                            {{ __('Please wait...') }}
                        </span>
                    </button>
                    <p x-show="message" x-cloak role="status" aria-live="polite"
                       class="mt-3 flex items-start gap-2 rounded-xl p-3 text-sm"
                       :class="messageOk ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300'">
                        <span x-show="messageOk">@svg('heroicon-m-check-circle', 'mt-0.5 size-5 shrink-0')</span>
                        <span x-show="!messageOk">@svg('heroicon-m-exclamation-triangle', 'mt-0.5 size-5 shrink-0')</span>
                        <span x-text="message"></span>
                    </p>
                    <p class="mt-3 text-center text-xs text-zinc-500 dark:text-zinc-400">{{ __('Your location and a selfie are saved with each punch.') }}</p>
                </div>
            </template>
        </div>

        <article x-show="(data.optional_holidays ?? []).length" x-cloak class="me-card p-4">
            <h3 class="flex items-center gap-2 font-semibold">@svg('heroicon-o-calendar-days', 'size-4.5 text-emerald-600 dark:text-emerald-400') {{ __('Optional holidays') }}</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Take the ones you need; on the others you work as usual.') }}</p>
            <ul class="mt-3 divide-y divide-zinc-100 dark:divide-white/10">
                <template x-for="h in data.optional_holidays ?? []" :key="h.id">
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate font-medium" x-text="h.name"></span>
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400" x-text="h.date"></span>
                        </span>
                        <button @click="toggleOptional(h)" class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition active:scale-[0.98]"
                                :class="h.taken ? 'bg-emerald-600 text-white' : 'bg-zinc-100 text-zinc-800 dark:bg-white/10 dark:text-zinc-100'"
                                x-text="h.taken ? '{{ __('Taking it ✓') }}' : '{{ __('Take this day') }}'"></button>
                    </li>
                </template>
            </ul>
        </article>

        <template x-for="a in data.announcements ?? []" :key="a.id">
            <article class="me-card relative overflow-hidden p-4 ps-5">
                <span aria-hidden="true" class="absolute inset-y-0 start-0 w-1 bg-amber-400"></span>
                <div class="flex items-start gap-3">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">@svg('heroicon-o-megaphone', 'size-4.5')</span>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold" x-text="a.title"></h3>
                        <p class="mt-1 text-sm leading-relaxed whitespace-pre-line text-zinc-600 dark:text-zinc-300" x-text="a.body"></p>
                        <button x-show="a.requires_ack && !a.acked" @click="ack(a)" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 px-3 py-2 text-sm font-medium text-white transition active:scale-[0.98] dark:bg-white dark:text-zinc-900">
                            @svg('heroicon-m-check', 'size-4') {{ __('I have read this') }}
                        </button>
                        <p x-show="a.requires_ack && a.acked" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400">@svg('heroicon-m-check-circle', 'size-4') {{ __('Acknowledged') }}</p>
                    </div>
                </div>
            </article>
        </template>

        <div class="me-card flex items-center gap-4 p-4">
            <a href="{{ route('me.qr') }}" target="_blank" class="shrink-0 rounded-xl bg-white p-2 ring-1 ring-zinc-950/10" aria-label="{{ __('Open QR badge full size') }}">
                <img src="{{ route('me.qr') }}" alt="" class="size-24">
            </a>
            <div class="min-w-0">
                <h3 class="font-semibold">{{ __('Kiosk badge') }}</h3>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Show this QR code to the kiosk camera. It changes every day.') }}</p>
            </div>
        </div>
    </section>

    {{-- ================= Month ================= --}}
    <section x-show="tab === 'month'" x-cloak x-transition.opacity.duration.150ms class="space-y-3 p-4" aria-labelledby="me-month-h">
        <div class="me-card p-4">
            <div class="mb-4 flex items-center justify-between">
                <button @click="shiftMonth(-1)" class="flex size-10 items-center justify-center rounded-full text-zinc-600 transition hover:bg-zinc-950/5 dark:text-zinc-300 dark:hover:bg-white/5" aria-label="{{ __('Previous month') }}">@svg('heroicon-m-chevron-left', 'size-5 rtl:rotate-180')</button>
                <h2 id="me-month-h" class="text-base font-semibold" x-text="monthLabel()"></h2>
                <button @click="shiftMonth(1)" class="flex size-10 items-center justify-center rounded-full text-zinc-600 transition hover:bg-zinc-950/5 dark:text-zinc-300 dark:hover:bg-white/5" aria-label="{{ __('Next month') }}">@svg('heroicon-m-chevron-right', 'size-5 rtl:rotate-180')</button>
            </div>

            <a :href="`/me/report.pdf?month=${month}`" x-show="month && month <= todayIso.slice(0, 7)"
               class="mb-4 flex items-center justify-center gap-2 rounded-xl bg-zinc-100 py-2.5 text-sm font-medium text-zinc-800 transition active:scale-[0.99] dark:bg-white/10 dark:text-zinc-100">
                @svg('heroicon-m-document-arrow-down', 'size-4.5') {{ __('Download my monthly report (PDF)') }}
            </a>

            <dl class="mb-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-emerald-50 py-2 dark:bg-emerald-500/10"><dt class="text-xs text-emerald-700 dark:text-emerald-400">{{ __('Present') }}</dt><dd class="text-lg font-semibold tabular-nums text-emerald-800 dark:text-emerald-300" x-text="monthCount('ok')">0</dd></div>
                <div class="rounded-xl bg-amber-50 py-2 dark:bg-amber-500/10"><dt class="text-xs text-amber-700 dark:text-amber-400">{{ __('Late / half') }}</dt><dd class="text-lg font-semibold tabular-nums text-amber-800 dark:text-amber-300" x-text="monthCount('warn')">0</dd></div>
                <div class="rounded-xl bg-rose-50 py-2 dark:bg-rose-500/10"><dt class="text-xs text-rose-700 dark:text-rose-400">{{ __('Absent') }}</dt><dd class="text-lg font-semibold tabular-nums text-rose-800 dark:text-rose-300" x-text="monthCount('bad')">0</dd></div>
            </dl>

            <div class="grid grid-cols-7 gap-1 text-center" role="grid">
                <template x-for="(w, i) in weekdays()" :key="i"><div class="pb-1 text-[0.6875rem] font-medium text-zinc-400 dark:text-zinc-500" x-text="w" role="columnheader"></div></template>
                <template x-for="n in leadBlanks()" :key="'b' + n"><div aria-hidden="true"></div></template>
                <template x-for="d in data.calendar ?? []" :key="d.date">
                    <button type="button" @click="selected = d" role="gridcell"
                            :aria-label="`${d.date} ${codeName(d.code)}`" :aria-pressed="selected?.date === d.date"
                            class="flex aspect-square flex-col items-center justify-center gap-0.5 rounded-xl text-xs transition"
                            :class="[
                                selected?.date === d.date ? 'ring-2 ring-brand dark:ring-emerald-400' : '',
                                d.date === todayIso ? 'font-semibold' : '',
                                codeTone(d.code).cell,
                            ]">
                        <span :class="d.date === todayIso ? 'text-brand dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400'" x-text="Number(d.date.slice(8))"></span>
                        <span class="text-[0.6875rem] font-semibold leading-none" :class="codeTone(d.code).text" x-text="d.code || ''"></span>
                    </button>
                </template>
            </div>
        </div>

        <div x-show="selected" x-cloak class="me-card p-4" aria-live="polite">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold" x-text="selected && dayLabel(selected.date)"></h3>
                <span class="me-chip" :class="selected && codeTone(selected.code).chip" x-text="selected && codeName(selected.code)"></span>
            </div>
            <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('In') }}</dt><dd class="font-semibold tabular-nums" x-text="selected?.in ?? '--:--'"></dd></div>
                <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Out') }}</dt><dd class="font-semibold tabular-nums" x-text="selected?.out ?? '--:--'"></dd></div>
                <div><dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Late') }}</dt><dd class="font-semibold tabular-nums" x-text="selected?.late ? `${selected.late} min` : '-'"></dd></div>
            </dl>
            <button @click="startCorrection(selected.date)" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-brand dark:text-emerald-400">
                @svg('heroicon-m-pencil-square', 'size-4') {{ __('Request a correction for this day') }}
            </button>
        </div>

        <details class="me-card group p-4 text-sm">
            <summary class="flex cursor-pointer list-none items-center justify-between font-medium">
                {{ __('What do the codes mean?') }}
                @svg('heroicon-m-chevron-down', 'size-5 text-zinc-400 transition-transform group-open:rotate-180')
            </summary>
            <ul class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2">
                <template x-for="c in ['P','L','HD','A','WO','H','OD','WFH','LV','UL','MO']" :key="c">
                    <li class="flex items-center gap-2"><span class="me-chip min-w-9 justify-center" :class="codeTone(c).chip" x-text="c"></span><span class="text-zinc-600 dark:text-zinc-300" x-text="codeName(c)"></span></li>
                </template>
            </ul>
        </details>
    </section>

    {{-- ================= Leave ================= --}}
    <section x-show="tab === 'leave'" x-cloak x-transition.opacity.duration.150ms class="space-y-3 p-4">
        <div class="-mx-4 flex snap-x scroll-px-4 gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]">
            <template x-for="b in data.balances ?? []" :key="b.code">
                <div class="me-card min-w-32 shrink-0 snap-start p-4">
                    <div class="truncate text-xs font-medium text-zinc-500 dark:text-zinc-400" x-text="b.name"></div>
                    <div class="mt-1 text-2xl font-semibold tracking-tight tabular-nums" x-text="b.remaining"></div>
                    <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('days left') }}</div>
                </div>
            </template>
        </div>

        <form @submit.prevent="applyLeave($event.target)" class="me-card space-y-4 p-5">
            <h2 class="text-base font-semibold">{{ __('Apply for leave') }}</h2>
            <div>
                <label for="lv-type" class="me-label">{{ __('Leave type') }}</label>
                <select id="lv-type" name="leave_type_id" required class="me-input">
                    <template x-for="t in leaveTypes" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="lv-from" class="me-label">{{ __('From') }}</label>
                    <input id="lv-from" type="date" name="from_date" required class="me-input" x-model="leaveFrom">
                </div>
                <div>
                    <label for="lv-to" class="me-label">{{ __('To') }}</label>
                    <input id="lv-to" type="date" name="to_date" required class="me-input" :min="leaveFrom">
                </div>
            </div>
            <div>
                <label for="lv-part" class="me-label">{{ __('Duration') }}</label>
                <select id="lv-part" name="day_part" class="me-input">
                    <option value="full">{{ __('Full day(s)') }}</option>
                    <option value="first_half">{{ __('First half') }}</option>
                    <option value="second_half">{{ __('Second half') }}</option>
                </select>
            </div>
            <div>
                <label for="lv-reason" class="me-label">{{ __('Reason') }} <span class="font-normal text-zinc-400">{{ __('(optional)') }}</span></label>
                <textarea id="lv-reason" name="reason" rows="2" class="me-input"></textarea>
            </div>
            <div>
                <label for="lv-file" class="me-label">{{ __('Attachment') }} <span class="font-normal text-zinc-400">{{ __('(optional)') }}</span></label>
                <input id="lv-file" type="file" name="attachment" accept="image/*,application/pdf"
                       class="block w-full text-sm text-zinc-500 file:me-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-800 dark:text-zinc-400 dark:file:bg-white/10 dark:file:text-zinc-100">
                <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Photo or PDF, up to 5 MB. A medical certificate for sick leave, for example.') }}</p>
            </div>
            <button class="me-btn" :disabled="busy">{{ __('Send request') }}</button>
        </form>

        <div class="me-card p-5">
            <h2 class="mb-1 text-base font-semibold">{{ __('My requests') }}</h2>
            <ul class="divide-y divide-zinc-950/[0.06] dark:divide-white/[0.06]">
                <template x-for="r in data.requests ?? []" :key="`${r.type}${r.from}${r.status}`">
                    <li class="flex items-center justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium"><span x-text="r.type"></span> <span class="text-zinc-400" x-text="r.days ? `· ${r.days} {{ __('day(s)') }}` : ''"></span></div>
                            <div class="text-xs text-zinc-500 tabular-nums dark:text-zinc-400" x-text="r.from === r.to ? r.from : `${r.from} {{ __('to') }} ${r.to}`"></div>
                        </div>
                        <span class="me-chip capitalize" :class="requestTone(r.status)" x-text="r.status"></span>
                    </li>
                </template>
            </ul>
            <p x-show="loaded && !(data.requests ?? []).length" class="py-4 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('No leave requests yet.') }}</p>
        </div>
    </section>

    {{-- ================= Correction ================= --}}
    <section x-show="tab === 'fix'" x-cloak x-transition.opacity.duration.150ms class="p-4">
        <form x-ref="fixForm" @submit.prevent="requestCorrection($event.target)" class="me-card space-y-4 p-5">
            <div>
                <h2 class="text-base font-semibold">{{ __('Request a correction') }}</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Forgot to punch, or worked away from the office? Your manager will review it.') }}</p>
            </div>
            <div>
                <label for="fx-date" class="me-label">{{ __('Date') }}</label>
                <input id="fx-date" type="date" name="date" required class="me-input" x-model="fixDate" :max="todayIso">
            </div>
            <fieldset>
                <legend class="me-label">{{ __('What happened?') }}</legend>
                <div class="grid gap-2">
                    @foreach (['add_punch' => [__('I forgot to punch'), 'heroicon-o-finger-print'], 'mark_od' => [__('Official duty outside the office'), 'heroicon-o-briefcase'], 'mark_wfh' => [__('I worked from home'), 'heroicon-o-home-modern']] as $value => [$label, $icon])
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl p-3 ring-1 transition"
                               :class="fixType === '{{ $value }}' ? 'bg-emerald-50 ring-2 ring-brand dark:bg-emerald-500/10 dark:ring-emerald-400' : 'ring-zinc-950/10 dark:ring-white/10'">
                            <input type="radio" name="type" value="{{ $value }}" x-model="fixType" class="sr-only">
                            @svg($icon, 'size-5 shrink-0 text-zinc-500 dark:text-zinc-400')
                            <span class="text-sm font-medium">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div x-show="fixType === 'add_punch'" class="grid grid-cols-2 gap-3">
                <div>
                    <label for="fx-time" class="me-label">{{ __('Time') }}</label>
                    <input id="fx-time" type="time" name="time" class="me-input" :required="fixType === 'add_punch'">
                </div>
                <div>
                    <label for="fx-dir" class="me-label">{{ __('Punch') }}</label>
                    <select id="fx-dir" name="direction" class="me-input">
                        <option value="out">{{ __('Out') }}</option><option value="in">{{ __('In') }}</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="fx-reason" class="me-label">{{ __('Reason') }}</label>
                <textarea id="fx-reason" name="reason" rows="3" required class="me-input"></textarea>
            </div>
            <button class="me-btn" :disabled="busy">{{ __('Send request') }}</button>
        </form>

        <div x-show="data.swaps?.enabled" x-cloak class="mt-4 space-y-3">
            <form @submit.prevent="requestSwap($event.target)" class="me-card space-y-4 p-5">
                <div>
                    <h2 class="text-base font-semibold">{{ __('Swap a shift') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Ask a colleague to swap shifts for one day. They accept first, then your manager or HR approves.') }}</p>
                </div>
                <div>
                    <label for="sw-date" class="me-label">{{ __('Date') }}</label>
                    <input id="sw-date" type="date" name="date" required class="me-input" :min="todayIso">
                </div>
                <div>
                    <label for="sw-who" class="me-label">{{ __('Swap with') }}</label>
                    <select id="sw-who" name="partner_employee_id" required class="me-input">
                        <option value="">{{ __('Choose a colleague') }}</option>
                        <template x-for="c in data.swaps?.colleagues ?? []" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                    </select>
                </div>
                <div>
                    <label for="sw-reason" class="me-label">{{ __('Reason') }}</label>
                    <input id="sw-reason" type="text" name="reason" maxlength="255" class="me-input">
                </div>
                <button class="me-btn" :disabled="busy">{{ __('Ask my colleague') }}</button>
            </form>

            <template x-for="s in data.swaps?.items ?? []" :key="s.id">
                <article class="me-card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold" x-text="`${s.date} · ${s.with}`"></p>
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400" x-text="s.shifts"></p>
                        </div>
                        <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium dark:bg-white/10" x-text="s.status"></span>
                    </div>
                    <div x-show="s.can_answer" class="mt-3 flex gap-2">
                        <button @click="answerSwap(s, true)" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white">{{ __('Accept') }}</button>
                        <button @click="answerSwap(s, false)" class="rounded-lg bg-zinc-100 px-3 py-2 text-sm font-medium dark:bg-white/10">{{ __('Decline') }}</button>
                    </div>
                </article>
            </template>
        </div>
    </section>

    <div x-show="toast" x-cloak role="status" aria-live="polite"
         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-2 opacity-0"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-y-2 opacity-0"
         class="fixed inset-x-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 mx-auto flex max-w-md items-center gap-2.5 rounded-xl bg-zinc-900 px-4 py-3 text-sm text-white shadow-lg dark:bg-zinc-100 dark:text-zinc-900">
        <span x-show="toastOk" class="text-emerald-400 dark:text-emerald-600">@svg('heroicon-m-check-circle', 'size-5')</span>
        <span x-show="!toastOk" class="text-rose-400 dark:text-rose-600">@svg('heroicon-m-exclamation-circle', 'size-5')</span>
        <span x-text="toast"></span>
    </div>

    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-zinc-950/[0.06] bg-white/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg dark:border-white/[0.06] dark:bg-zinc-900/90" aria-label="{{ __('Sections') }}">
        <div class="mx-auto grid max-w-lg grid-cols-4">
            @foreach ($tabs as $key => [$label, $icon, $iconActive])
                <button @click="tab = '{{ $key }}'" :aria-current="tab === '{{ $key }}' ? 'page' : null"
                        class="flex flex-col items-center gap-1 pt-2 pb-2.5 text-[0.6875rem] font-medium transition"
                        :class="tab === '{{ $key }}' ? 'text-brand dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400'">
                    <span class="flex h-7 w-14 items-center justify-center rounded-full transition"
                          :class="tab === '{{ $key }}' && 'bg-emerald-100 dark:bg-emerald-500/15'">
                        <span x-show="tab !== '{{ $key }}'">@svg($icon, 'size-5')</span>
                        <span x-show="tab === '{{ $key }}'" x-cloak>@svg($iconActive, 'size-5')</span>
                    </span>
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </nav>
</div>
@endsection
