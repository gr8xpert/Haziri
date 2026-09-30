<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Settings\TenantSettings;
use Closure;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** White-label (plan feature `white_label`): tenant logo + primary colour on the panel, per request. */
final class ApplyTenantBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            $settings = app(TenantSettings::class);
            if ($settings->featureEnabled('white_label')) {
                $color = (string) $settings->get('brand_color', '');
                if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    FilamentColor::register(['primary' => Color::hex($color)]);
                }
                $logo = (string) $settings->get('brand_logo', '');
                if (str_starts_with($logo, 'data:image/')) {
                    Filament::getPanel('tenant')->brandLogo($logo)->brandLogoHeight('2.5rem');
                }
            }
        }

        return $next($request);
    }
}
