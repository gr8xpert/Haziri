<?php

declare(strict_types=1);

namespace App\Enums;

/** Shared by leave_requests and attendance_corrections. */
enum RequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
