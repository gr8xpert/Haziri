<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A shift swap: an employee asks from the app → the colleague accepts (Partner → Pending) or declines →
 * HR / the manager approves or rejects. Requests HR enters start at Pending.
 */
enum ShiftSwapStatus: string implements HasColor, HasLabel
{
    case Partner = 'partner';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::Partner => __('Waiting for colleague'),
            self::Pending => __('Waiting for approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Declined => __('Colleague declined'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Partner, self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected, self::Declined => 'danger',
        };
    }
}
