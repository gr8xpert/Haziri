<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Central routes: only on the central domains (marketing root, admin., adms., api.).
| Tenant routes live in routes/tenant.php; the super-admin panel registers itself on admin.*.
*/
foreach (config('tenancy.central_domains') as $domain) {
    if (in_array($domain, [config('hazri.admin_domain'), config('hazri.adms_domain'), config('hazri.api_domain')], true)) {
        continue; // admin "/" is the Filament central panel; adms/api only serve their endpoints
    }
    Route::domain($domain)->group(function () use ($domain) {
        Route::get('/', fn () => view('welcome'))->name('central.home.' . $domain);
    });
}
