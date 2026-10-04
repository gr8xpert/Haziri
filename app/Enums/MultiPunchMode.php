<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MultiPunchMode: string implements HasLabel
{
    case FirstLast = 'first_last';
    case Paired = 'paired';

    public function getLabel(): string
    {
        return match ($this) {
            self::FirstLast => __('First in, last out'),
            self::Paired => __('Every in/out pair'),
        };
    }
}
