<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkMode: string
{
    case Office = 'office';
    case Field = 'field';
    case Remote = 'remote';
}
