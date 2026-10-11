<?php

declare(strict_types=1);

namespace App\Support;

/** A number of minutes in words people read easily: "45 minutes", "1 hour", "4 hours 32 minutes". */
final class Duration
{
    public static function human(int $minutes): string
    {
        $minutes = abs($minutes);
        if ($minutes < 60) {
            return $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
        }
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $text = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');

        return $rest === 0 ? $text : $text . ' ' . self::human($rest);
    }
}
