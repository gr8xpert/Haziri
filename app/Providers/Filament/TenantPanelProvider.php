<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Tenant\Pages\Dashboard;
use App\Filament\Tenant\Pages\SwitchCompany;
use App\Http\Middleware\ApplyTenantBranding;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\ScopeToUserBranches;
use App\Support\Brand;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenantPanelProvider extends PanelProvider
{
    /**
     * Emerald shifted one step darker, so shade 600 is the brand green (#047857, emerald-700).
     * Filament puts white text on shade 600 only when it passes WCAG AA; stock emerald-600 does not,
     * which turned primary buttons into light mint with dark text.
     */
    private const BRAND_PRIMARY = [
        50 => 'oklch(0.979 0.021 166.113)',
        100 => 'oklch(0.95 0.052 163.051)',
        200 => 'oklch(0.905 0.093 164.15)',
        300 => 'oklch(0.845 0.143 164.978)',
        400 => 'oklch(0.696 0.17 162.48)',
        500 => 'oklch(0.596 0.145 163.225)',
        600 => 'oklch(0.508 0.118 165.612)',
        700 => 'oklch(0.432 0.095 166.913)',
        800 => 'oklch(0.378 0.077 168.94)',
        900 => 'oklch(0.32 0.064 170.5)',
        950 => 'oklch(0.262 0.051 172.552)',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant')
            ->path('admin')
            ->login()
            ->brandName(fn () => tenant('company_name') ?? Brand::name())
            ->userMenuItems([
                Action::make('switchCompany')->label(__('Switch company'))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->url(fn () => SwitchCompany::getUrl())
                    ->visible(fn () => SwitchCompany::canAccess()),
            ])
            ->favicon('/hazri-icon.svg')
            ->font('Instrument Sans')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.hooks.sidebar-accordion'))
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('18rem')
            ->maxContentWidth(Width::Full)
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->colors([
                'primary' => self::BRAND_PRIMARY,
                'gray' => Color::Zinc,
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])
            ->discoverResources(in: app_path('Filament/Tenant/Resources'), for: 'App\Filament\Tenant\Resources')
            ->discoverPages(in: app_path('Filament/Tenant/Pages'), for: 'App\Filament\Tenant\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Tenant/Widgets'), for: 'App\Filament\Tenant\Widgets')
            ->widgets([])
            ->middleware([
                // Tenancy first: the session user and everything after it live in the tenant DB.
                // Persistent so Livewire update requests are tenant-aware too.
                IdentifyTenant::class,
                PreventAccessFromCentralDomains::class,
                ApplyTenantBranding::class,
            ], isPersistent: true)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Branch managers only see their branches (feature multi_branch); persistent so Livewire updates are scoped too.
                ScopeToUserBranches::class,
            ], isPersistent: true);
    }
}
