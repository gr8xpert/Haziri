<?php

declare(strict_types=1);

namespace App\Actions\Punches;

enum IngestOutcome: string
{
    /** New valid punch stored; recompute queued. */
    case Stored = 'stored';
    /** Same device/PIN/time already stored — ignored (safe replays). */
    case AlreadyStored = 'already_stored';
    /** Stored as `duplicate`: within dedupe_window_sec of another punch. */
    case DoubleTap = 'double_tap';
    /** Stored as `unmapped`: no employee has this PIN yet. */
    case Unmapped = 'unmapped';
}
