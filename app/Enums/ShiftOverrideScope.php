<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShiftOverrideScope: string implements HasLabel
{
    case Tenant = 'tenant';
    case Branch = 'branch';
    case Department = 'department';
    case Shift = 'shift';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tenant => __('Whole company'),
            self::Branch => __('Branch'),
            self::Department => __('Department'),
            self::Shift => __('Shift'),
        };
    }
}
