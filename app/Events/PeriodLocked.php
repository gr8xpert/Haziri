<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AttendancePeriod;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

final class PeriodLocked
{
    use Dispatchable;

    public function __construct(public readonly AttendancePeriod $period, public readonly User $by) {}
}
