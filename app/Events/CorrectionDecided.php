<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Events\Dispatchable;

final class CorrectionDecided
{
    use Dispatchable;

    public function __construct(public readonly AttendanceCorrection $correction, public readonly bool $final) {}
}
