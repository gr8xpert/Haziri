<?php

declare(strict_types=1);

namespace App\Enums;

enum PunchStatus: string
{
    case Valid = 'valid';
    case Duplicate = 'duplicate';
    case Unmapped = 'unmapped';
    case Rejected = 'rejected';
    /** Mobile punch outside the geofence, waiting for HR approval (spec §6.6). Not counted until approved. */
    case Pending = 'pending';
}
