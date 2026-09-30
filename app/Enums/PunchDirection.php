<?php

declare(strict_types=1);

namespace App\Enums;

enum PunchDirection: string
{
    case In = 'in';
    case Out = 'out';
    case Unknown = 'unknown';
}
