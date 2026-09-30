<?php

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Services\SelfService\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Employee PWA login: email/password or phone + OTP (spec §6.6). */
final class AuthController
{
    public function show(): View
    {
        return view('me.login', ['company' => tenant('company_name')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($data + ['is_active' => true], true)) {
            return back()->withErrors(['email' => __('Wrong email or password.')])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('me.home'));
    }

    public function sendOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'min:10', 'max:20']]);
        $otp->send($data['phone']);

        return back()->with(['otp_phone' => $data['phone'], 'status' => __('If this number is registered, a code has been sent.')]);
    }

    public function verifyOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string'], 'code' => ['required', 'digits:6']]);
        $user = $otp->verify($data['phone'], $data['code']);
        if ($user === null) {
            return back()->with('otp_phone', $data['phone'])->withErrors(['code' => __('That code is wrong or expired.')]);
        }
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('me.home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('me.login');
    }
}
