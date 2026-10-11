<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Setting;
use App\Services\Settings\TenantSettings;
use Illuminate\Validation\ValidationException;

/**
 * White-label branding of the current tenant. Used by the client's Branding page and by the super-admin
 * (inside $tenant->run()). Images are stored inline as data: URLs so every surface can show them without a
 * public, tenant-shared disk.
 */
final class SaveBranding
{
    public const TEXT_KEYS = ['app_name', 'assistant_name', 'email_from_name', 'brand_color'];

    private const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    private const MAX_BYTES = 150_000;

    public function __construct(private readonly TenantSettings $settings) {}

    /**
     * @param  array<string, mixed>  $values  text keys (blank = clear); logo / icon as raw image bytes (null = keep)
     */
    public function __invoke(array $values, ?string $logoBytes = null, ?string $iconBytes = null, ?bool $locked = null): void
    {
        $color = $values['brand_color'] ?? null;
        if (filled($color) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) !== 1) {
            throw ValidationException::withMessages(['brand_color' => __('Use a colour like #1d4ed8.')]);
        }
        foreach (['brand_logo' => $logoBytes, 'brand_icon' => $iconBytes] as $key => $bytes) {
            if ($bytes !== null) {
                Setting::updateOrCreate(['key' => $key], ['value' => self::dataUrl($bytes, $key)]);
            }
        }
        foreach (self::TEXT_KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $text = trim((string) $values[$key]);
                Setting::updateOrCreate(['key' => $key], ['value' => $text === '' ? null : mb_substr($text, 0, 60)]);
            }
        }
        if ($locked !== null) {
            Setting::updateOrCreate(['key' => 'branding_locked'], ['value' => $locked]);
        }
        $this->settings->flush();
    }

    /** Removes the uploaded logo or icon, so the default look (or, for the icon, the logo) is used again. */
    public function removeImage(string $which): void
    {
        $key = match ($which) {
            'logo' => 'brand_logo',
            'icon' => 'brand_icon',
            default => throw new \InvalidArgumentException("Unknown branding image {$which}"),
        };
        Setting::query()->where('key', $key)->delete();
        $this->settings->flush();
    }

    private static function dataUrl(string $bytes, string $field): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, self::IMAGE_TYPES, true) || strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field === 'brand_icon' ? 'icon' : 'logo' => __('Use a PNG, JPEG or WebP under 150 KB.')]);
        }

        return "data:{$mime};base64," . base64_encode($bytes);
    }
}
