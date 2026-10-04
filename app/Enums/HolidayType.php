<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum HolidayType: string implements HasLabel
{
    case Gazetted = 'gazetted';
    case Company = 'company';
    case Optional = 'optional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gazetted => __('Gazetted'),
            self::Company => __('Company'),
            self::Optional => __('Optional'),
        };
    }
}
