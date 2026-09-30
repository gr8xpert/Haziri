<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use App\Models\User;
use App\Services\Notifications\ChannelRegistry;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RenderedMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Phone + OTP login for the employee PWA (spec §6.6). 6-digit code, 5 minutes, 5 attempts,
 * max 3 codes per phone per 10 minutes. Sent over WhatsApp when enabled, otherwise SMS.
 * Cache keys include the tenant id (the cache store is shared, see ADR 0001).
 */
final class OtpService
{
    public function __construct(private readonly ChannelRegistry $channels) {}

    public static function normalise(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '92' . substr($digits, 1); // 03xx… → 923xx…
        }

        return $digits;
    }

    public function send(string $phone): void
    {
        $phone = self::normalise($phone);
        $limiterKey = $this->key('send', $phone);
        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            throw ValidationException::withMessages(['phone' => __('Too many codes requested. Try again in a few minutes.')]);
        }
        RateLimiter::hit($limiterKey, 600);

        $user = $this->userFor($phone);
        if ($user === null) {
            return; // don't reveal whether the number exists
        }
        $code = (string) random_int(100000, 999999);
        Cache::put($this->key('code', $phone), ['hash' => Hash::make($code), 'user' => $user->id, 'tries' => 0], now()->addMinutes(5));

        $channel = $this->channels->resolve('whatsapp') ?? $this->channels->resolve('sms');
        $to = new Recipient($user->name, null, $phone, $user->locale ?? 'en', $user->id);
        $channel?->send($to, new RenderedMessage('otp', null, __('Your Hazri login code is :code. It expires in 5 minutes.', ['code' => $code]), ['code' => $code]));
    }

    public function verify(string $phone, string $code): ?User
    {
        $phone = self::normalise($phone);
        $key = $this->key('code', $phone);
        $entry = Cache::get($key);
        if ($entry === null) {
            return null;
        }
        if ($entry['tries'] >= 5) {
            Cache::forget($key);

            return null;
        }
        if (! Hash::check($code, $entry['hash'])) {
            $entry['tries']++;
            Cache::put($key, $entry, now()->addMinutes(5));

            return null;
        }
        Cache::forget($key);

        return User::find($entry['user']);
    }

    private function userFor(string $phone): ?User
    {
        return User::query()->where('is_active', true)->get()
            ->first(fn (User $u) => self::normalise((string) ($u->phone ?? $u->employee?->phone ?? '')) === $phone);
    }

    private function key(string $kind, string $phone): string
    {
        return "otp:{$kind}:" . tenant('id') . ":{$phone}";
    }
}
