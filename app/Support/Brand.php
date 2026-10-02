<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Settings\TenantSettings;

/**
 * What a client sees as the product's name, icon and colour. With the `white_label` feature the client's own
 * branding replaces ours everywhere they can see (panel, /me app, kiosk, PDFs, emails, SMS, the AI assistant).
 * Without it the platform brand (config app.name) is shown.
 */
final class Brand
{
    public const PLATFORM_ICON = '/hazri-icon.svg';

    /** Shown for a white-labelled client that has not uploaded an icon or logo: no platform branding. */
    public const NEUTRAL_ICON = '/brand/app-icon.svg';

    public static function whiteLabel(): bool
    {
        return tenancy()->initialized && app(TenantSettings::class)->featureEnabled('white_label');
    }

    public static function name(): string
    {
        if (! self::whiteLabel()) {
            return (string) config('hazri.platform_name', 'Hazri');
        }

        return self::setting('app_name') ?? (string) (tenant('company_name') ?: __('Attendance'));
    }

    public static function assistantName(): string
    {
        return self::whiteLabel() ? (self::setting('assistant_name') ?? __('Ask AI')) : __('Ask :name', ['name' => self::name()]);
    }

    /** Small square icon for browser tabs, the phone home screen and headers. */
    public static function iconUrl(): string
    {
        if (! self::whiteLabel()) {
            return self::PLATFORM_ICON;
        }

        $uploaded = self::uploadedIcon();

        return $uploaded !== null ? '/app-icon?v=' . substr(md5($uploaded), 0, 8) : self::NEUTRAL_ICON;
    }

    /** The client's icon (or, failing that, logo) as a data: URL, when white-labelled and uploaded. */
    public static function uploadedIcon(): ?string
    {
        if (! self::whiteLabel()) {
            return null;
        }
        $image = self::setting('brand_icon') ?? self::setting('brand_logo');

        return $image !== null && str_starts_with($image, 'data:image/') ? $image : null;
    }

    public static function logo(): ?string
    {
        return self::whiteLabel() ? self::setting('brand_logo') : null;
    }

    /** #rrggbb or null for the default theme. */
    public static function color(): ?string
    {
        $color = self::whiteLabel() ? self::setting('brand_color') : null;

        return $color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : null;
    }

    /** "Powered by …" lines and the platform name in footers only appear for ordinary clients. */
    public static function showPlatform(): bool
    {
        return ! self::whiteLabel();
    }

    /**
     * Host a client is told to use: 'cname' (target for their own domain), 'adms' (machines), 'api' (sync agent).
     * White-labelled clients get the neutral domain when one is configured.
     */
    public static function host(string $purpose): string
    {
        $neutral = self::whiteLabel() ? config('hazri.neutral_domain') : null;

        return match ($purpose) {
            'cname' => $neutral ?: tenant('slug') . '.' . config('hazri.central_domain'),
            'adms' => $neutral ? "adms.{$neutral}" : (string) config('hazri.adms_domain'),
            'api' => $neutral ? "api.{$neutral}" : (string) config('hazri.api_domain'),
        };
    }

    private static function setting(string $key): ?string
    {
        $value = app(TenantSettings::class)->get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
