<?php

declare(strict_types=1);

namespace App\Filament\Tenant;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Attendance = 'attendance';
    case People = 'people';
    case TimeRules = 'time_rules';
    case Leave = 'leave';
    case Devices = 'devices';
    case Reports = 'reports';
    case Payroll = 'payroll';
    case Organisation = 'organisation';
    case System = 'system';

    public function getLabel(): string
    {
        return __('nav.' . $this->value);
    }
}
