<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Device;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired once per outage (spec §6.1); notifications subscribe to it. */
final class DeviceWentOffline
{
    use Dispatchable;

    public function __construct(public readonly Device $device) {}
}
