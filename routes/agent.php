<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AgentController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

/*
| Bridge-agent API (spec §13.1). Served on api.{central domain}; the bearer key selects the tenant.
| Loaded by MachineSyncServiceProvider.
*/
Route::prefix('api/v1/agent')
    ->middleware([ThrottleRequests::using('agent'), AuthenticateAgent::class, SubstituteBindings::class])
    ->name('agent.')
    ->group(function () {
        Route::post('punches', [AgentController::class, 'punches'])->name('punches');
        Route::post('users', [AgentController::class, 'users'])->name('users');
        Route::get('commands', [AgentController::class, 'commands'])->name('commands');
        Route::post('commands/{command}/result', [AgentController::class, 'commandResult'])->whereNumber('command')->name('commands.result');
        Route::post('heartbeat', [AgentController::class, 'heartbeat'])->name('heartbeat');
    });
