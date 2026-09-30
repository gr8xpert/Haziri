<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveDayPart: string
{
    case Full = 'full';
    case FirstHalf = 'first_half';
    case SecondHalf = 'second_half';

    public function isHalf(): bool
    {
        return $this !== self::Full;
    }
}
