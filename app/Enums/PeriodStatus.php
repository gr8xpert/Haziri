<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PeriodStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Review = 'review';
    case Locked = 'locked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Review => __('In review'),
            self::Locked => __('Locked'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'gray',
            self::Review => 'warning',
            self::Locked => 'success',
        };
    }
}
