@extends('me.layout')

@section('title', __('Sign in'))

@section('content')
<main class="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4 py-10" x-data="{ mode: '{{ session('otp_phone') ? 'otp' : 'phone' }}' }">
    <div class="mb-8 text-center">
        <img src="/hazri-icon.svg" alt="" class="mx-auto h-14 w-14">
        <h1 class="mt-3 text-xl font-semibold">{{ $company }}</h1>
        <p class="text-sm text-gray-500">{{ __('Attendance self-service') }}</p>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>
    @endif

    <div class="mb-4 grid grid-cols-2 rounded-lg bg-gray-100 p-1 text-sm">
        <button type="button" @click="mode = 'phone'" :class="mode !== 'email' && 'bg-white shadow'" class="rounded-md py-2">{{ __('Phone') }}</button>
        <button type="button" @click="mode = 'email'" :class="mode === 'email' && 'bg-white shadow'" class="rounded-md py-2">{{ __('Email') }}</button>
    </div>

    {{-- Phone: request a code, then enter it --}}
    <form x-show="mode === 'phone'" method="POST" action="{{ route('me.otp.send') }}" class="space-y-3">
        @csrf
        <input name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="03xx xxxxxxx" value="{{ old('phone') }}"
               class="w-full rounded-lg border border-gray-300 px-3 py-3 text-lg">
        <button class="w-full rounded-lg bg-brand py-3 font-medium text-white">{{ __('Send code') }}</button>
    </form>

    <form x-show="mode === 'otp'" x-cloak method="POST" action="{{ route('me.otp.verify') }}" class="space-y-3">
        @csrf
        <input type="hidden" name="phone" value="{{ session('otp_phone') }}">
        <p class="text-sm text-gray-600">{{ __('Enter the 6-digit code sent to :p', ['p' => session('otp_phone')]) }}</p>
        <input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required autofocus
               class="w-full rounded-lg border border-gray-300 px-3 py-3 text-center text-2xl tracking-[0.5em]">
        <button class="w-full rounded-lg bg-brand py-3 font-medium text-white">{{ __('Sign in') }}</button>
        <button type="button" @click="mode = 'phone'" class="w-full text-sm text-gray-500">{{ __('Use another number') }}</button>
    </form>

    <form x-show="mode === 'email'" x-cloak method="POST" action="{{ route('me.login.password') }}" class="space-y-3">
        @csrf
        <input name="email" type="email" autocomplete="username" required placeholder="{{ __('Email') }}" value="{{ old('email') }}"
               class="w-full rounded-lg border border-gray-300 px-3 py-3">
        <input name="password" type="password" autocomplete="current-password" required placeholder="{{ __('Password') }}"
               class="w-full rounded-lg border border-gray-300 px-3 py-3">
        <button class="w-full rounded-lg bg-brand py-3 font-medium text-white">{{ __('Sign in') }}</button>
    </form>
</main>
@endsection
