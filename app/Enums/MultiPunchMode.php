<?php

declare(strict_types=1);

namespace App\Enums;

enum MultiPunchMode: string
{
    case FirstLast = 'first_last';
    case Paired = 'paired';
}
