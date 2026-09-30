<?php

declare(strict_types=1);

namespace App\Services\Devices;

use App\Enums\PunchDirection;
use App\Enums\VerifyType;

/** ZKTeco punch-state and verify-mode codes (ATTLOG columns 3 and 4). */
final class ZkCodes
{
    /** 0 check-in, 1 check-out, 2 break-out, 3 break-in, 4 OT-in, 5 OT-out. */
    public static function direction(int $status): PunchDirection
    {
        return match ($status) {
            0, 3, 4 => PunchDirection::In,
            1, 2, 5 => PunchDirection::Out,
            default => PunchDirection::Unknown,
        };
    }

    /** 0 password, 1 fingerprint, 2/4 card, 15 face, 25 palm (treated as face-class biometrics). */
    public static function verify(int $verify): VerifyType
    {
        return match ($verify) {
            0 => VerifyType::Password,
            1 => VerifyType::Fingerprint,
            2, 4 => VerifyType::Card,
            15, 25 => VerifyType::Face,
            3 => VerifyType::Pin,
            default => VerifyType::None,
        };
    }
}
