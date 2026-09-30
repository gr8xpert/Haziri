<?php

declare(strict_types=1);

namespace App\Services\Devices;

use App\Enums\PunchDirection;
use App\Support\Excel\Spreadsheet;

/**
 * Parses USB exports (spec §6.3):
 *  - ZKTeco `*_attlog.dat`: header-less, tab (or space) separated: PIN, "YYYY-MM-DD HH:MM:SS", status, verify, workcode, …
 *    Extra/missing trailing columns are tolerated (K40/K50 firmwares differ).
 *  - Hazri CSV/XLSX template: employee_code_or_pin, date, time, direction
 *
 * Returned timestamps are device-local wall-clock strings; the importer converts them with the device timezone.
 */
final class UsbLogParser
{
    /**
     * @return array{rows: list<array{pin: string, local: string, direction: PunchDirection, verify: int, workcode: ?string, line: int}>, errors: list<string>, format: string}
     */
    public function parse(string $path, string $originalName = ''): array
    {
        $ext = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));

        return in_array($ext, ['csv', 'xlsx'], true) ? $this->parseTemplate($path) : $this->parseDat($path);
    }

    /** @return array{rows: list<array<string, mixed>>, errors: list<string>, format: string} */
    public function parseDat(string $path): array
    {
        $rows = [];
        $errors = [];
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ['rows' => [], 'errors' => ['Cannot open file'], 'format' => 'attlog'];
        }
        $n = 0;
        while (($line = fgets($handle)) !== false) {
            $n++;
            $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? '');
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^(\S+)\s+(\d{4}-\d{2}-\d{2})\s+(\d{1,2}:\d{2}(?::\d{2})?)(?:\s+(\d+))?(?:\s+(\d+))?(?:\s+(\S+))?/', $line, $m)) {
                $errors[] = "Line {$n}: not an attendance line";

                continue;
            }
            $rows[] = [
                'pin' => $m[1],
                'local' => $m[2] . ' ' . (strlen($m[3]) <= 5 ? $m[3] . ':00' : $m[3]),
                'direction' => ZkCodes::direction((int) ($m[4] ?? 255)),
                'verify' => (int) ($m[5] ?? 0),
                'workcode' => isset($m[6]) && $m[6] !== '0' ? $m[6] : null,
                'line' => $n,
            ];
        }
        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors, 'format' => 'attlog'];
    }

    /** @return array{rows: list<array<string, mixed>>, errors: list<string>, format: string} */
    public function parseTemplate(string $path): array
    {
        $rows = [];
        $errors = [];
        foreach (Spreadsheet::readAssoc($path) as $i => $r) {
            $line = $i + 2;
            $pin = $r['employee_code_or_pin'] ?? $r['pin'] ?? $r['code'] ?? '';
            $date = $r['date'] ?? '';
            $time = $r['time'] ?? '';
            if ($pin === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
                $errors[] = "Row {$line}: need employee_code_or_pin, date (YYYY-MM-DD) and time (HH:MM)";

                continue;
            }
            $rows[] = [
                'pin' => $pin,
                'local' => $date . ' ' . (strlen($time) <= 5 ? $time . ':00' : $time),
                'direction' => PunchDirection::tryFrom(strtolower($r['direction'] ?? '')) ?? PunchDirection::Unknown,
                'verify' => 0,
                'workcode' => null,
                'line' => $line,
                'is_code' => true,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors, 'format' => 'template'];
    }
}
