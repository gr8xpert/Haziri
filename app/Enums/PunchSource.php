<?php

declare(strict_types=1);

namespace App\Enums;

enum PunchSource: string
{
    case Adms = 'adms';
    case Agent = 'agent';
    case Usb = 'usb';
    case Kiosk = 'kiosk';
    case Mobile = 'mobile';
    case Manual = 'manual';
    case Api = 'api';
}
