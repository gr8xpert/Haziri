<?php

declare(strict_types=1);

namespace App\Enums;

/** devices.type — how a device delivers punches (spec §5.2). */
enum DevicePunchSourceType: string
{
    case Adms = 'adms';
    case Agent = 'agent';
    case Usb = 'usb';
    case Kiosk = 'kiosk';
    case Manual = 'manual';
    case Mobile = 'mobile';
}
