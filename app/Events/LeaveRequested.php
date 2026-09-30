<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;

final class LeaveRequested
{
    use Dispatchable;

    public function __construct(public readonly LeaveRequest $request) {}
}
