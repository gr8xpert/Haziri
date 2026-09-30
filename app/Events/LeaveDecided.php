<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired on every decision step; $final tells whether the request is now approved/rejected. */
final class LeaveDecided
{
    use Dispatchable;

    public function __construct(public readonly LeaveRequest $request, public readonly bool $final) {}
}
