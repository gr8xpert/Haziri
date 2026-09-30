<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Settings\TenantSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Per request / per job; also forgotten whenever tenancy switches (TenancyServiceProvider).
        $this->app->scoped(TenantSettings::class);
        $this->app->singleton(\App\Services\Notifications\ChannelRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\NotificationSubscriber::class);
    }
}
