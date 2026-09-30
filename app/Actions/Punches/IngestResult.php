<?php

declare(strict_types=1);

namespace App\Actions\Punches;

use App\Models\Punch;

final readonly class IngestResult
{
    public function __construct(public IngestOutcome $outcome, public ?Punch $punch) {}
}
