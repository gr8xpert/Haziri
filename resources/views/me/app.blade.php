@extends('me.layout')

@section('title', __('My attendance'))

@section('content')
<div x-data="meApp({ canPunch: @js($canPunch), leaveTypes: @js($leaveTypes) })" x-init="load()" class="mx-auto max-w-lg pb-24">
    <header class="sticky top-0 z-10 flex items-center justify-between bg-brand px-4 py-3 text-white">
        <div>
            <div class="text-xs opacity-80">{{ $company }}</div>
            <div class="font-semibold">{{ $employee->fullName() }}</div>
        </div>
        <form method="POST" action="{{ route('me.logout') }}">@csrf<button class="text-sm opacity-90">{{ __('Sign out') }}</button></form>
    </header>

    {{-- Today + punch --}}
    <section x-show="tab === 'home'" class="space-y-4 p-4">
        <div class="rounded-2xl bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Today') }}</div>
            <div class="mt-1 flex items-baseline gap-4">
                <div><span class="text-xs text-gray-500">{{ __('In') }}</span> <span class="text-2xl font-semibold" x-text="data.today?.in ?? '—'"></span></div>
                <div><span class="text-xs text-gray-500">{{ __('Out') }}</span> <span class="text-2xl font-semibold" x-text="data.today?.out ?? '—'"></span></div>
            </div>
            <p x-show="data.today?.late_min" class="mt-1 text-sm text-amber-600" x-text="`{{ __('Late by') }} ${data.today?.late_min} min`"></p>

            <template x-if="canPunch">
                <div class="mt-4">
                    <video x-ref="video" x-show="camera" playsinline muted class="mb-3 aspect-[4/3] w-full rounded-xl bg-black object-cover"></video>
                    <button @click="punch()" :disabled="busy"
                            class="w-full rounded-xl py-4 text-lg font-semibold text-white disabled:opacity-60"
                            :class="data.next_direction === 'out' ? 'bg-rose-600' : 'bg-brand'"
                            x-text="busy ? '{{ __('Please wait…') }}' : (data.next_direction === 'out' ? '{{ __('Punch out') }}' : '{{ __('Punch in') }}')"></button>
                    <p x-show="message" x-text="message" class="mt-2 text-center text-sm" :class="messageOk ? 'text-emerald-700' : 'text-red-600'"></p>
                </div>
            </template>
        </div>

        <template x-for="a in data.announcements ?? []" :key="a.id">
            <div class="rounded-2xl bg-amber-50 p-4 text-sm">
                <div class="font-semibold" x-text="a.title"></div>
                <p class="mt-1 whitespace-pre-line" x-text="a.body"></p>
                <button x-show="a.requires_ack && !a.acked" @click="ack(a)" class="mt-2 rounded-lg bg-amber-600 px-3 py-1.5 text-white">{{ __('I have read this') }}</button>
            </div>
        </template>

        <div class="rounded-2xl bg-white p-4 text-center shadow-sm">
            <div class="mb-2 text-sm text-gray-500">{{ __('Your QR badge for the kiosk (changes daily)') }}</div>
            <img src="{{ route('me.qr') }}" alt="QR" class="mx-auto h-44 w-44">
        </div>
    </section>

    {{-- Month calendar --}}
    <section x-show="tab === 'month'" x-cloak class="p-4">
        <div class="mb-3 flex items-center justify-between">
            <button @click="shiftMonth(-1)" class="rounded-lg bg-white px-3 py-1.5 shadow-sm">‹</button>
            <div class="font-semibold" x-text="data.month"></div>
            <button @click="shiftMonth(1)" class="rounded-lg bg-white px-3 py-1.5 shadow-sm">›</button>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-xs">
            <template x-for="d in data.calendar ?? []" :key="d.date">
                <div class="rounded-lg bg-white p-1.5 shadow-sm">
                    <div class="text-gray-400" x-text="d.date.slice(8)"></div>
                    <div class="font-semibold" :class="codeColour(d.code)" x-text="d.code || '·'"></div>
                </div>
            </template>
        </div>
        <p class="mt-3 text-xs text-gray-500">P {{ __('present') }} · L {{ __('late') }} · A {{ __('absent') }} · HD {{ __('half day') }} · WO {{ __('weekly off') }} · H {{ __('holiday') }}</p>
    </section>

    {{-- Leave --}}
    <section x-show="tab === 'leave'" x-cloak class="space-y-4 p-4">
        <div class="grid grid-cols-3 gap-2">
            <template x-for="b in data.balances ?? []" :key="b.code">
                <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                    <div class="text-xs text-gray-500" x-text="b.name"></div>
                    <div class="text-xl font-semibold" x-text="b.remaining"></div>
                </div>
            </template>
        </div>
        <form @submit.prevent="applyLeave($event.target)" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            <div class="font-semibold">{{ __('Apply for leave') }}</div>
            <select name="leave_type_id" required class="w-full rounded-lg border border-gray-300 p-2.5">
                <template x-for="t in leaveTypes" :key="t.id"><option :value="t.id" x-text="t.name"></option></template>
            </select>
            <div class="grid grid-cols-2 gap-2">
                <input type="date" name="from_date" required class="rounded-lg border border-gray-300 p-2.5">
                <input type="date" name="to_date" required class="rounded-lg border border-gray-300 p-2.5">
            </div>
            <select name="day_part" class="w-full rounded-lg border border-gray-300 p-2.5">
                <option value="full">{{ __('Full day(s)') }}</option>
                <option value="first_half">{{ __('First half') }}</option>
                <option value="second_half">{{ __('Second half') }}</option>
            </select>
            <textarea name="reason" rows="2" placeholder="{{ __('Reason') }}" class="w-full rounded-lg border border-gray-300 p-2.5"></textarea>
            <input type="file" name="attachment" accept="image/*,application/pdf" class="text-sm">
            <button class="w-full rounded-lg bg-brand py-2.5 font-medium text-white">{{ __('Submit') }}</button>
        </form>
        <div class="rounded-2xl bg-white p-4 shadow-sm">
            <div class="mb-2 font-semibold">{{ __('My requests') }}</div>
            <template x-for="r in data.requests ?? []">
                <div class="flex justify-between border-t py-2 text-sm first:border-0">
                    <span x-text="`${r.type} ${r.from} → ${r.to}`"></span>
                    <span class="font-medium" x-text="r.status"></span>
                </div>
            </template>
        </div>
    </section>

    {{-- Correction --}}
    <section x-show="tab === 'fix'" x-cloak class="p-4">
        <form @submit.prevent="requestCorrection($event.target)" class="space-y-2 rounded-2xl bg-white p-4 shadow-sm">
            <div class="font-semibold">{{ __('Request a correction') }}</div>
            <input type="date" name="date" required class="w-full rounded-lg border border-gray-300 p-2.5">
            <select name="type" x-model="fixType" class="w-full rounded-lg border border-gray-300 p-2.5">
                <option value="add_punch">{{ __('Forgot to punch') }}</option>
                <option value="mark_od">{{ __('Official duty (outside office)') }}</option>
                <option value="mark_wfh">{{ __('Worked from home') }}</option>
            </select>
            <div x-show="fixType === 'add_punch'" class="grid grid-cols-2 gap-2">
                <input type="time" name="time" class="rounded-lg border border-gray-300 p-2.5">
                <select name="direction" class="rounded-lg border border-gray-300 p-2.5">
                    <option value="out">{{ __('Out') }}</option><option value="in">{{ __('In') }}</option>
                </select>
            </div>
            <textarea name="reason" rows="2" required placeholder="{{ __('Reason') }}" class="w-full rounded-lg border border-gray-300 p-2.5"></textarea>
            <button class="w-full rounded-lg bg-brand py-2.5 font-medium text-white">{{ __('Send') }}</button>
        </form>
    </section>

    <p x-show="toast" x-text="toast" x-cloak class="fixed inset-x-4 bottom-20 rounded-xl bg-gray-900 p-3 text-center text-sm text-white"></p>

    <nav class="fixed inset-x-0 bottom-0 mx-auto grid max-w-lg grid-cols-4 border-t bg-white text-xs">
        @foreach (['home' => __('Today'), 'month' => __('Month'), 'leave' => __('Leave'), 'fix' => __('Correction')] as $key => $label)
            <button @click="tab = '{{ $key }}'" class="py-3" :class="tab === '{{ $key }}' ? 'font-semibold text-brand' : 'text-gray-500'">{{ $label }}</button>
        @endforeach
    </nav>
</div>
@endsection
