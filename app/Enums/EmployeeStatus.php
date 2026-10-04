<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmployeeStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Left = 'left';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::OnLeave => __('On leave'),
            self::Left => __('Left'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnLeave => 'warning',
            self::Left => 'gray',
        };
    }
}
