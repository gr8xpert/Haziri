<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * How a report cell is shown on screen and in the PDF: amounts get thousand separators (87,125). Excel keeps the
 * plain number so it can still add up. Codes, IDs and CNICs are strings and stay as they are.
 */
final class CellFormat
{
    public static function show(mixed $value): string
    {
        if (is_int($value) || (is_float($value) && floor($value) === $value)) {
            return abs((int) $value) >= 1000 ? number_format((int) $value) : (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2), '0'), '.'); // 1,234.5 / 14.3
        }

        return (string) ($value ?? '');
    }
}
