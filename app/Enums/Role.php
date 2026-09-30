<?php

declare(strict_types=1);

namespace App\Enums;

/** Tenant roles (spec §8). super_admin lives in the central DB and is not a spatie role. */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Hr = 'hr';
    case Manager = 'manager';
    case Receptionist = 'receptionist';
    case Employee = 'employee';
}
