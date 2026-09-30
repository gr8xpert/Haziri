<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores kiosk / mobile selfies (data URLs) in the tenant's private storage (spec §14.6: storage/app/tenant/{id}/photos).
 * JPEG/PNG/WebP only, max 1.5 MB. Served only through signed URLs.
 */
final class PunchPhotoStore
{
    private const MAX_BYTES = 1_500_000;

    public function store(?string $dataUrl): ?string
    {
        if ($dataUrl === null || $dataUrl === '') {
            return null;
        }
        if (! preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#', $dataUrl, $m)) {
            throw ValidationException::withMessages(['photo' => __('The photo must be a JPEG, PNG or WebP image.')]);
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['photo' => __('The photo is invalid or too large.')]);
        }
        if (@getimagesizefromstring($bytes) === false) {
            throw ValidationException::withMessages(['photo' => __('The photo could not be read.')]);
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $path = 'photos/' . now()->format('Y/m/d') . '/' . Str::uuid() . '.' . $ext;
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }
}
