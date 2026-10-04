<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeaveDayPart: string implements HasLabel
{
    case Full = 'full';
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function isHalf(): bool
    {
        return $this !== self::Full;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Full => __('Full day'),
            self::FirstHalf => __('First half'),
            self::SecondHalf => __('Second half'),
        };
    }
}
