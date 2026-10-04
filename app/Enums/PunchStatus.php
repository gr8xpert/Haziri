<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PunchStatus: string implements HasColor, HasLabel
{
    case Valid = 'valid';
    case Duplicate = 'duplicate';
    case Unmapped = 'unmapped';
    case Rejected = 'rejected';
    /** Mobile punch outside the geofence, waiting for HR approval (spec §6.6). Not counted until approved. */
    case Pending = 'pending';

    public function getLabel(): string
    {
        return match ($this) {
            self::Valid => __('Valid'),
            self::Duplicate => __('Duplicate'),
            self::Unmapped => __('Unknown PIN'),
            self::Rejected => __('Rejected'),
            self::Pending => __('Waiting for approval'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Duplicate => 'gray',
            self::Unmapped => 'warning',
            self::Rejected => 'danger',
            self::Pending => 'warning',
        };
    }
}
