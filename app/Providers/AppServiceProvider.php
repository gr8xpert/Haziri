<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AiModel;
use App\Listeners\NotificationSubscriber;
use App\Models\Employee;
use App\Notifications\PanelResetPassword;
use App\Services\Ai\AnthropicAiModel;
use App\Services\Billing\BillingService;
use App\Services\Notifications\ChannelRegistry;
use App\Services\Notifications\Channels\HttpSmsChannel;
use App\Services\Notifications\Channels\MetaWhatsAppChannel;
use App\Services\Settings\TenantSettings;
use App\Support\BranchAccess;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(AiModel::class, AnthropicAiModel::class);
        $this->app->singleton(ChannelRegistry::class, function () {
            $registry = new ChannelRegistry;
            $registry->extend('whatsapp', 'meta', MetaWhatsAppChannel::class);
            $registry->extend('sms', 'http', HttpSmsChannel::class);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::subscribe(NotificationSubscriber::class);

        // Queued work (also when the queue runs synchronously inside a branch manager's request) sees every branch.
        Event::listen(JobProcessing::class, fn () => BranchAccess::suspend());
        foreach ([JobProcessed::class, JobFailed::class, JobExceptionOccurred::class] as $done) {
            Event::listen($done, fn () => BranchAccess::resume());
        }
        // Password-reset mail sent in the request with the client's branding, not queued (PanelResetPassword).
        $this->app->bind(FilamentResetPassword::class, fn ($app, array $params) => new PanelResetPassword($params['token']));

        // Plan limit on active employees (spec §9 phase 4: usage enforcement).
        Employee::creating(fn () => BillingService::assertEmployeeCapacity());

        $this->configureDateFormats();
    }

    /**
     * Day-first dates everywhere (Pakistan), whatever the browser's language: pickers show 30/09/2026 instead of the
     * browser's native mm/dd/yyyy, and tables / detail views show 30 Sep 2026.
     */
    private function configureDateFormats(): void
    {
        DateTimePicker::configureUsing(function (DateTimePicker $picker): void {
            if ($picker instanceof TimePicker) {
                return;
            }
            $picker->native(false)->firstDayOfWeek(1)
                ->displayFormat($picker instanceof DatePicker ? 'd/m/Y' : 'd/m/Y H:i');
        });
        Table::configureUsing(fn (Table $table) => $table->defaultDateDisplayFormat('d M Y')->defaultDateTimeDisplayFormat('d M Y, H:i'));
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultDateDisplayFormat('d M Y')->defaultDateTimeDisplayFormat('d M Y, H:i'));
    }
}
