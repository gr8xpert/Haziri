<?php

declare(strict_types=1);

namespace App\Enums;

enum PunchStatus: string
{
    case Valid = 'valid';
    case Duplicate = 'duplicate';
    case Unmapped = 'unmapped';
    case Rejected = 'rejected';
}
