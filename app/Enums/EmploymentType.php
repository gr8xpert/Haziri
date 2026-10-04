<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmploymentType: string implements HasLabel
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Probation = 'probation';
    case DailyWage = 'daily_wage';
    case Intern = 'intern';

    public function getLabel(): string
    {
        return match ($this) {
            self::Permanent => __('Permanent'),
            self::Contract => __('Contract'),
            self::Probation => __('Probation'),
            self::DailyWage => __('Daily wage'),
            self::Intern => __('Intern'),
        };
    }
}
