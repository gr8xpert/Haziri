<?php

declare(strict_types=1);

namespace App\Filament\AvatarProviders;

use App\Support\Brand;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials avatar rendered locally as an SVG data URI.
 *
 * Replaces Filament's ui-avatars.com default, which sent every user's name to a third party
 * and drew a near-black circle that disappeared on the dark theme.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model | Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $segment), 0, 1))
            ->filter()
            ->take(2)
            ->join('');

        [$background, $foreground] = Filament::getCurrentPanel()?->getId() === 'central'
            ? ['#b45309', '#ffffff']
            : [Brand::color() ?? '#047857', '#ffffff'];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            . '<rect width="64" height="64" fill="' . $background . '"/>'
            . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="' . $foreground . '" '
            . 'font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="26" font-weight="600">'
            . e(mb_strtoupper($initials))
            . '</text></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
