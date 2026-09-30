<?php

declare(strict_types=1);

namespace App\Enums;

enum CorrectionType: string
{
    case AddPunch = 'add_punch';
    case RemovePunch = 'remove_punch';
    case ChangeStatus = 'change_status';
    case MarkOd = 'mark_od';
    case MarkWfh = 'mark_wfh';
}
