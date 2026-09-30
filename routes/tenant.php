<?php

declare(strict_types=1);

use App\Http\Controllers\KioskController;
use App\Http\Controllers\Me\AuthController;
use App\Http\Controllers\Me\MeController;
use App\Http\Middleware\EnsureEmployee;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
| Tenant routes ({slug}.hazri.pk or a custom domain). Company admin panel: Filament at /admin.
| Employee PWA: /me. Tablet kiosk: /kiosk/{token}.
*/
Route::middleware([
    'web',
    IdentifyTenant::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', fn () => redirect('/admin'));

    // Super-admin "log in as owner" (spec §8); token is single-use, 60 s TTL, and audited centrally.
    Route::get('/impersonate/{token}', fn (string $token) => UserImpersonation::makeResponse($token))
        ->middleware('throttle:10,1')->name('tenant.impersonate');

    // ---- Employee PWA (spec §6.6, §13.2) ----
    Route::get('/manifest.webmanifest', [MeController::class, 'manifest'])->name('me.manifest');
    Route::prefix('me')->name('me.')->group(function () {
        Route::get('login', [AuthController::class, 'show'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.password');
        Route::post('otp', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1')->name('otp.send');
        Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('otp.verify');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::middleware(EnsureEmployee::class)->group(function () {
            Route::get('/', [MeController::class, 'home'])->name('home');
            Route::get('attendance', [MeController::class, 'summary'])->name('attendance');
            Route::post('punch', [MeController::class, 'punch'])->middleware('throttle:6,1')->name('punch');
            Route::post('leave', [MeController::class, 'leave'])->name('leave');
            Route::post('corrections', [MeController::class, 'correction'])->name('corrections');
            Route::post('announcements/{announcement}/ack', [MeController::class, 'ack'])->name('announcements.ack');
            Route::get('qr.svg', [MeController::class, 'qr'])->name('qr');
        });
    });

    // Punch photos: private storage, only via short-lived signed URLs (spec §15).
    Route::get('/photos/punch/{punch}', function (App\Models\Punch $punch) {
        abort_if($punch->photo_path === null || ! Illuminate\Support\Facades\Storage::disk('local')->exists($punch->photo_path), 404);

        return Illuminate\Support\Facades\Storage::disk('local')->response($punch->photo_path, null, ['Cache-Control' => 'private, max-age=600']);
    })->middleware('signed')->name('tenant.punch-photo');

    // Printable visitor badge (receptionist, logged in to the panel).
    Route::get('/visitors/{visitor}/badge', function (App\Models\Visitor $visitor) {
        abort_unless(auth()->user()?->can('visitors.manage'), 403);
        $photo = $visitor->photo_path && Illuminate\Support\Facades\Storage::disk('local')->exists($visitor->photo_path)
            ? 'data:image/jpeg;base64,' . base64_encode(Illuminate\Support\Facades\Storage::disk('local')->get($visitor->photo_path))
            : null;

        return view('visitors.badge', ['visitor' => $visitor, 'photo' => $photo, 'company' => tenant('company_name')]);
    })->middleware('auth')->name('tenant.visitor-badge');

    // ---- Tablet kiosk (spec §6.5) ----
    Route::prefix('kiosk/{token}')->name('kiosk.')->where(['token' => '[A-Za-z0-9]{40}'])->group(function () {
        Route::get('/', [KioskController::class, 'show'])->name('show');
        Route::get('config', [KioskController::class, 'config'])->name('config');
        Route::post('punch', [KioskController::class, 'punch'])->middleware('throttle:120,1')->name('punch')
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    });
});
