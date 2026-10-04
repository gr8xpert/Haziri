<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WorkMode: string implements HasLabel
{
    case Office = 'office';
    case Field = 'field';
    case Remote = 'remote';

    public function getLabel(): string
    {
        return match ($this) {
            self::Office => __('Office'),
            self::Field => __('Field'),
            self::Remote => __('Remote'),
        };
    }
}
