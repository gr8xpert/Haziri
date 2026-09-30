<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Review = 'review';
    case Locked = 'locked';
}
