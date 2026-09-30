<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IdentifyTenant;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
| Tenant routes ({slug}.hazri.pk or a custom domain). The company admin panel is Filament at /admin.
| Employee PWA (/me) and kiosk (/kiosk/{token}) routes are added in phase 3.
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
});
