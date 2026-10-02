<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Company-wide excuse for one date (traffic jam, strike, heavy rain).
 * Late: arrivals that day are not marked late. Absent: the whole day is excused — no late, no half day,
 * and anyone who did not come is "exempted" instead of absent.
 */
enum ExemptionType: string implements HasLabel
{
    case Late = 'late';
    case Absent = 'absent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Late => __('Late arrivals excused'),
            self::Absent => __('Whole day excused (absent exempted)'),
        };
    }
}
