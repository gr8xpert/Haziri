@extends('me.layout')

@section('title', __('Sign in'))

@section('content')
<main class="relative isolate flex min-h-dvh flex-col px-5 pt-[max(env(safe-area-inset-top),2.5rem)] pb-[max(env(safe-area-inset-bottom),2rem)]"
      x-data="{ mode: '{{ session('otp_phone') ? 'otp' : ($errors->has('email') || old('email') ? 'email' : 'phone') }}' }">
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-80 bg-[radial-gradient(70%_100%_at_50%_0%,rgb(4_120_87/0.16),transparent)] dark:bg-[radial-gradient(70%_100%_at_50%_0%,rgb(52_211_153/0.12),transparent)]"></div>

    <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center">
        <div class="mb-8 text-center">
            <img src="{{ \App\Support\Brand::iconUrl() }}" alt="" class="mx-auto size-14 rounded-2xl shadow-sm">
            <h1 class="mt-4 text-2xl font-semibold tracking-tight text-balance">{{ $company }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Sign in to see your attendance, apply for leave and punch in.') }}</p>
        </div>

        @if (session('status'))
            <div role="status" class="mb-4 flex items-start gap-2.5 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 ring-1 ring-emerald-600/15 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20">
                @svg('heroicon-m-check-circle', 'mt-0.5 size-5 shrink-0')
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div role="alert" class="mb-4 flex items-start gap-2.5 rounded-xl bg-rose-50 p-3 text-sm text-rose-700 ring-1 ring-rose-600/15 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/20">
                @svg('heroicon-m-exclamation-circle', 'mt-0.5 size-5 shrink-0')
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <div class="me-card p-5">
            <div x-show="mode !== 'otp'" role="tablist" aria-label="{{ __('Sign-in method') }}" class="mb-5 grid grid-cols-2 rounded-xl bg-zinc-100 p-1 text-sm font-medium dark:bg-white/5">
                <button type="button" role="tab" :aria-selected="mode === 'phone'" @click="mode = 'phone'"
                        :class="mode === 'phone' ? 'bg-white text-zinc-950 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg py-2 transition">
                    @svg('heroicon-m-device-phone-mobile', 'size-4') {{ __('Phone') }}
                </button>
                <button type="button" role="tab" :aria-selected="mode === 'email'" @click="mode = 'email'"
                        :class="mode === 'email' ? 'bg-white text-zinc-950 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg py-2 transition">
                    @svg('heroicon-m-envelope', 'size-4') {{ __('Email') }}
                </button>
            </div>

            {{-- Phone: request a code, then enter it --}}
            <form x-show="mode === 'phone'" method="POST" action="{{ route('me.otp.send') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="me-phone" class="me-label">{{ __('Mobile number') }}</label>
                    <input id="me-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="03xx xxxxxxx" value="{{ old('phone') }}"
                           class="me-input text-lg tracking-wide">
                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">{{ __('We will send you a 6-digit sign-in code.') }}</p>
                </div>
                <button class="me-btn">{{ __('Send code') }}</button>
            </form>

            <form x-show="mode === 'otp'" x-cloak method="POST" action="{{ route('me.otp.verify') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="phone" value="{{ session('otp_phone') }}">
                <div>
                    <label for="me-code" class="me-label">{{ __('Enter the 6-digit code sent to :p', ['p' => session('otp_phone')]) }}</label>
                    <input id="me-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required autofocus
                           class="me-input text-center font-semibold text-2xl tracking-[0.5em] tabular-nums">
                </div>
                <button class="me-btn">{{ __('Sign in') }}</button>
                <button type="button" @click="mode = 'phone'" class="w-full py-1 text-sm font-medium text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">{{ __('Use another number') }}</button>
            </form>

            <form x-show="mode === 'email'" x-cloak method="POST" action="{{ route('me.login.password') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="me-email" class="me-label">{{ __('Email') }}</label>
                    <input id="me-email" name="email" type="email" autocomplete="username" required value="{{ old('email') }}" class="me-input">
                </div>
                <div>
                    <label for="me-password" class="me-label">{{ __('Password') }}</label>
                    <input id="me-password" name="password" type="password" autocomplete="current-password" required class="me-input">
                </div>
                <button class="me-btn">{{ __('Sign in') }}</button>
            </form>
        </div>
    </div>

    @if (\App\Support\Brand::showPlatform())
        <p class="mt-8 text-center text-xs text-zinc-400 dark:text-zinc-500">{{ __('Powered by :name', ['name' => \App\Support\Brand::name()]) }}</p>
    @endif
</main>
@endsection
