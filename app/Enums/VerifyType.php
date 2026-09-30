<?php

declare(strict_types=1);

namespace App\Enums;

enum VerifyType: string
{
    case Fingerprint = 'fp';
    case Face = 'face';
    case Card = 'card';
    case Pin = 'pin';
    case Password = 'password';
    case Photo = 'photo';
    case None = 'none';
}
