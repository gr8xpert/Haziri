<?php

declare(strict_types=1);

use App\Http\Controllers\HelpController;
use Illuminate\Support\Facades\Route;

/*
| The user guide (resources/docs), loaded on the main site and on every company address. Unnamed on purpose:
| it is registered once per domain, and pages link to each other by path.
*/
Route::prefix('help')->controller(HelpController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('quick', 'quick');
    Route::get('troubleshooting', 'troubleshooting');
    Route::get('search.json', 'search');
    Route::get('for', 'forScreen');
    Route::get('{slug}', 'show')->where('slug', '[a-z0-9-]+');
});
