<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PublicApiController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\Me\AuthController;
use App\Http\Controllers\Me\MeController;
use App\Http\Controllers\SwitchCompanyController;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureEmployee;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Announcement;
use App\Models\Punch;
use App\Models\Visitor;
use App\Support\Brand;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
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

    // White-label app icon (tab, phone home screen): the client's uploaded icon/logo.
    Route::get('/app-icon', function () {
        [$meta, $data] = array_pad(explode(',', Brand::uploadedIcon() ?? '', 2), 2, '');
        abort_unless(preg_match('#^data:(image/(png|jpeg|webp));base64$#', $meta, $m) === 1, 404);

        return response(base64_decode($data, true) ?: abort(404), 200, ['Content-Type' => $m[1], 'Cache-Control' => 'public, max-age=3600']);
    })->name('brand.icon');

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
    Route::get('/photos/punch/{punch}', function (Punch $punch) {
        $path = (string) $punch->photo_path;
        abort_if(! str_starts_with($path, 'photos/') || str_contains($path, '..') || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($punch->photo_path, null, ['Cache-Control' => 'private, max-age=600']);
    })->middleware('signed')->name('tenant.punch-photo');

    // Multi-company: jump to another company this login is linked to.
    Route::get('/switch/{tenantId}', SwitchCompanyController::class)
        ->middleware(['auth', 'throttle:10,1'])->name('tenant.switch');

    // Printable visitor badge (receptionist, logged in to the panel).
    Route::get('/visitors/{visitor}/badge', function (Visitor $visitor) {
        abort_unless(auth()->user()?->can('visitors.manage'), 403);
        $path = (string) $visitor->photo_path;
        $safe = str_starts_with($path, 'visitor-photos/') && ! str_contains($path, '..');
        $photo = $safe && Storage::disk('local')->exists($path)
            ? 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get($path))
            : null;

        return view('visitors.badge', ['visitor' => $visitor, 'photo' => $photo, 'company' => tenant('company_name')]);
    })->middleware('auth')->name('tenant.visitor-badge');

    // Printable A4 notice for the notice board (workers without the app), English + Urdu headings.
    Route::get('/announcements/{announcement}/print', function (Announcement $announcement) {
        abort_unless(auth()->user()?->can('announcements.manage'), 403);

        return view('announcements.print', ['announcement' => $announcement, 'company' => tenant('company_name')]);
    })->middleware('auth')->name('tenant.announcement-print');

    // ---- Public API v1 (spec §13.3): bearer token, per-token rate limit, no session/CSRF ----
    Route::prefix('api/v1')->name('api.')
        ->withoutMiddleware([PreventRequestForgery::class])
        ->middleware(ThrottleRequests::using('public-api'))
        ->group(function () {
            $read = AuthenticateApiToken::class . ':read';
            Route::get('employees', [PublicApiController::class, 'employees'])->middleware($read);
            Route::get('attendance', [PublicApiController::class, 'attendance'])->middleware($read);
            Route::get('leave/balances', [PublicApiController::class, 'leaveBalances'])->middleware($read);
            Route::get('periods/{ym}/payroll-input', [PublicApiController::class, 'payrollInput'])->middleware($read);
            Route::post('punches', [PublicApiController::class, 'punch'])
                ->middleware(AuthenticateApiToken::class . ':punches:write');
        });

    // ---- Tablet kiosk (spec §6.5) ----
    Route::prefix('kiosk/{token}')->name('kiosk.')->where(['token' => '[A-Za-z0-9]{40}'])->group(function () {
        Route::get('/', [KioskController::class, 'show'])->name('show');
        Route::get('config', [KioskController::class, 'config'])->name('config');
        Route::post('punch', [KioskController::class, 'punch'])->middleware('throttle:120,1')->name('punch')
            ->withoutMiddleware(PreventRequestForgery::class);
        Route::post('announcements/{announcement}/ack/{employee}', [KioskController::class, 'ack'])
            ->middleware(['signed', 'throttle:120,1'])->name('announcement-ack')->withoutMiddleware(PreventRequestForgery::class);
    });
});
