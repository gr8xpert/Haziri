<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PunchDirection: string implements HasLabel
{
    case In = 'in';
    case Out = 'out';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => __('In'),
            self::Out => __('Out'),
            self::Unknown => __('Unknown'),
        };
    }
}
