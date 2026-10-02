<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Brand;
use Closure;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * White-label (plan feature `white_label`): the client's logo, icon, colour and product name on the panel, in page
 * titles and in mail, per request. See App\Support\Brand for what the client sees everywhere else.
 */
final class ApplyTenantBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Brand::whiteLabel()) {
            $panel = Filament::getPanel('tenant');
            if ($color = Brand::color()) {
                FilamentColor::register(['primary' => Color::hex($color)]);
            }
            $logo = (string) Brand::logo();
            if (str_starts_with($logo, 'data:image/')) {
                $panel->brandLogo($logo)->brandLogoHeight('2.5rem');
            }
            $panel->favicon(Brand::iconUrl());
            config(['app.name' => Brand::name()]);
        }

        return $next($request);
    }
}
