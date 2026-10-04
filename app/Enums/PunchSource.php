<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PunchSource: string implements HasLabel
{
    case Adms = 'adms';
    case Agent = 'agent';
    case Usb = 'usb';
    case Kiosk = 'kiosk';
    case Mobile = 'mobile';
    case Manual = 'manual';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adms => __('Machine (cloud)'),
            self::Agent => __('Machine (office PC agent)'),
            self::Usb => __('USB import'),
            self::Kiosk => __('Kiosk'),
            self::Mobile => __('Mobile'),
            self::Manual => __('Manual'),
            self::Api => __('API'),
        };
    }
}
