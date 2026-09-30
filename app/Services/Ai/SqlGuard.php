<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Validator for model-written SQL (spec §12.1c). Rejects anything that is not a single, plain SELECT over the
 * whitelisted views. This is the first line of defence; the query also runs in a READ ONLY transaction with a
 * statement timeout, under the read-only DB user when configured.
 */
final class SqlGuard
{
    public const VIEWS = ['v_attendance_days', 'v_employees', 'v_leave', 'v_punches_summary'];

    public const MAX_ROWS = 5000;

    /** @return array{ok: bool, sql?: string, error?: string} */
    public static function check(string $sql): array
    {
        $sql = trim($sql);
        $sql = rtrim($sql, "; \t\n\r");

        if ($sql === '' || strlen($sql) > 4000) {
            return ['ok' => false, 'error' => 'empty or too long'];
        }
        if (str_contains($sql, ';')) {
            return ['ok' => false, 'error' => 'only one statement is allowed'];
        }
        if (preg_match('/(--|#|\/\*)/', $sql)) {
            return ['ok' => false, 'error' => 'comments are not allowed'];
        }
        if (! preg_match('/^\s*(select|with)\b/i', $sql)) {
            return ['ok' => false, 'error' => 'must be a SELECT'];
        }
        if (preg_match('/\b(insert|update|delete|replace|merge|alter|create|drop|truncate|rename|grant|revoke|call|do|handler|load|lock|unlock|set|use|show|describe|explain|into|outfile|dumpfile|sleep|benchmark|load_file|get_lock|release_lock|information_schema|performance_schema|mysql|sys)\b/i', $sql)) {
            return ['ok' => false, 'error' => 'forbidden keyword'];
        }
        if (preg_match('/[`"]/', $sql) || preg_match('/\b\w+\s*\.\s*\w+\s*\.\s*\w+/', $sql)) {
            return ['ok' => false, 'error' => 'quoted identifiers and schema-qualified names are not allowed'];
        }

        // Every table referenced after FROM / JOIN must be a whitelisted view (or a CTE defined in the query).
        preg_match_all('/\bwith\s+(\w+)\s+as\s*\(|,\s*(\w+)\s+as\s*\(/i', $sql, $cte);
        $ctes = array_map('strtolower', array_filter(array_merge($cte[1], $cte[2])));
        preg_match_all('/\b(?:from|join)\s+(\(?\s*select\b|\w+(?:\.\w+)?)/i', $sql, $m);
        foreach ($m[1] as $ref) {
            if (preg_match('/^\(?\s*select/i', $ref)) {
                continue; // derived table; its own FROM is checked by this same loop
            }
            $name = strtolower($ref);
            if (str_contains($name, '.') || (! in_array($name, self::VIEWS, true) && ! in_array($name, $ctes, true))) {
                return ['ok' => false, 'error' => "table '{$ref}' is not allowed"];
            }
        }
        if ($m[1] === []) {
            return ['ok' => false, 'error' => 'no FROM clause'];
        }

        // LIMIT ≤ MAX_ROWS (added when missing).
        if (preg_match('/\blimit\s+(\d+)(\s*,\s*(\d+))?\s*$/i', $sql, $lim)) {
            $n = (int) ($lim[3] ?? $lim[1]);
            if ($n > self::MAX_ROWS) {
                return ['ok' => false, 'error' => 'LIMIT above ' . self::MAX_ROWS];
            }
        } else {
            $sql .= ' LIMIT ' . self::MAX_ROWS;
        }

        return ['ok' => true, 'sql' => $sql];
    }
}
