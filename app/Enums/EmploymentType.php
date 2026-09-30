<?php

declare(strict_types=1);

namespace App\Enums;

enum EmploymentType: string
{
    case Permanent = 'permanent';
    case Contract = 'contract';
    case Probation = 'probation';
    case DailyWage = 'daily_wage';
    case Intern = 'intern';
}
