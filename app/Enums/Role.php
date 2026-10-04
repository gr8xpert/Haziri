<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Tenant roles (spec §8). super_admin lives in the central DB and is not a spatie role. */
enum Role: string implements HasLabel
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Hr = 'hr';
    case Manager = 'manager';
    case BranchManager = 'branch_manager';
    case Receptionist = 'receptionist';
    case Employee = 'employee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::Hr => __('HR'),
            self::Manager => __('Manager'),
            self::BranchManager => __('Branch manager'),
            self::Receptionist => __('Receptionist'),
            self::Employee => __('Employee'),
        };
    }
}
