<?php

declare(strict_types=1);

namespace App\Support\Excel;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/** Thin wrapper over openspout so the rest of the app never touches its API directly. */
final class Spreadsheet
{
    /**
     * Rows of the first sheet as arrays keyed by the (lower-cased, trimmed) header row.
     *
     * @return list<array<string, string>>
     */
    public static function readAssoc(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = match ($extension) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw new RuntimeException("Unsupported file type: .{$extension}"),
        };

        $reader->open($path);
        $headers = null;
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(fn ($v) => self::stringify($v), $row->toArray());
                    if ($headers === null) {
                        $headers = array_map(fn ($h) => strtolower(trim($h)), $values);

                        continue;
                    }
                    if (implode('', $values) === '') {
                        continue;
                    }
                    $rows[] = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), ''));
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int|string, scalar|null>>  $rows
     */
    public static function write(string $path, array $headers, iterable $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers, (new Style)->setFontBold()));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }
        $writer->close();
    }

    private static function stringify(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }
}
