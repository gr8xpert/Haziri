<?php

declare(strict_types=1);

namespace App\Enums;

enum HolidayType: string
{
    case Gazetted = 'gazetted';
    case Company = 'company';
    case Optional = 'optional';
}
