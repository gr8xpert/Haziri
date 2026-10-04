<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VerifyType: string implements HasLabel
{
    case Fingerprint = 'fp';
    case Face = 'face';
    case Card = 'card';
    case Pin = 'pin';
    case Password = 'password';
    case Photo = 'photo';
    case None = 'none';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fingerprint => __('Fingerprint'),
            self::Face => __('Face'),
            self::Card => __('Card'),
            self::Pin => __('PIN'),
            self::Password => __('Password'),
            self::Photo => __('Photo'),
            self::None => __('None'),
        };
    }
}
