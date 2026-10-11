<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** devices.type — how a device delivers punches (spec §5.2). */
enum DevicePunchSourceType: string implements HasLabel
{
    case Adms = 'adms';
    case Agent = 'agent';
    case Usb = 'usb';
    case Kiosk = 'kiosk';
    case Manual = 'manual';
    case Mobile = 'mobile';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adms => __('Machine (cloud)'),
            self::Agent => __('Machine (office PC agent)'),
            self::Usb => __('USB import'),
            self::Kiosk => __('Kiosk'),
            self::Manual => __('Manual'),
            self::Mobile => __('Mobile'),
        };
    }

    /**
     * The machine types added under Devices; kiosk tablets are made under Kiosks.
     *
     * @return list<self>
     */
    public static function machineTypes(): array
    {
        return [self::Adms, self::Agent, self::Usb];
    }
}
