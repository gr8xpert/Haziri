<?php

declare(strict_types=1);

namespace App\Enums;

enum ShiftOverrideScope: string
{
    case Tenant = 'tenant';
    case Branch = 'branch';
    case Department = 'department';
    case Shift = 'shift';
}
