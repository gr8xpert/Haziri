<?php

declare(strict_types=1);

namespace App\Support\Excel;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use ZipArchive;

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
        return self::readWithHeaders($path)['rows'];
    }

    /**
     * The first sheet: its header row as written (trimmed) and the rows keyed by the lower-cased header.
     *
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    public static function readWithHeaders(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = match ($extension) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader,
            default => throw new RuntimeException("Unsupported file type: .{$extension}"),
        };

        $reader->open($path);
        $headers = null;
        $keys = [];
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(fn ($v) => self::stringify($v), $row->toArray());
                    if ($headers === null) {
                        $headers = array_map(fn ($h) => trim($h), $values);
                        $keys = array_map(fn ($h) => strtolower($h), $headers);

                        continue;
                    }
                    if (implode('', $values) === '') {
                        continue;
                    }
                    $rows[] = array_combine($keys, array_pad(array_slice($values, 0, count($keys)), count($keys), ''));
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return ['headers' => $headers ?? [], 'rows' => $rows];
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

    /**
     * A workbook with several sheets (bold header row each). `lists` adds a drop-down to whole columns of that sheet:
     * 0-based column index => allowed values (Excel data validation; openspout cannot write it, so it is added after).
     *
     * @param  list<array{name: string, headers: list<string>, rows: iterable<array<int|string, scalar|null>>, widths?: array<int, float>, lists?: array<int, list<string>>}>  $sheets
     */
    public static function writeWorkbook(string $path, array $sheets): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        foreach ($sheets as $i => $sheet) {
            $current = $i === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $current->setName($sheet['name']);
            foreach ($sheet['widths'] ?? [] as $column => $width) {
                $current->setColumnWidth($width, $column + 1);
            }
            $writer->addRow(Row::fromValues($sheet['headers'], (new Style)->setFontBold()));
            foreach ($sheet['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_values($row)));
            }
        }
        $writer->close();

        foreach ($sheets as $i => $sheet) {
            if (! empty($sheet['lists'])) {
                self::addDropDowns($path, $i + 1, $sheet['lists']);
            }
        }
    }

    /** @param array<int, list<string>> $lists 0-based column => allowed values, applied to rows 2..1000 */
    private static function addDropDowns(string $path, int $sheetNumber, array $lists): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Could not reopen the workbook.');
        }
        $entry = "xl/worksheets/sheet{$sheetNumber}.xml";
        $xml = (string) $zip->getFromName($entry);
        $rules = '';
        foreach ($lists as $column => $values) {
            $letter = self::columnLetter($column);
            $formula = htmlspecialchars('"' . implode(',', $values) . '"', ENT_XML1);
            $rules .= "<dataValidation type=\"list\" allowBlank=\"1\" showErrorMessage=\"1\" sqref=\"{$letter}2:{$letter}1000\"><formula1>{$formula}</formula1></dataValidation>";
        }
        $block = '<dataValidations count="' . count($lists) . '">' . $rules . '</dataValidations>';
        // Schema order: dataValidations come after sheetData / autoFilter / mergeCells and before these.
        $at = strlen($xml);
        foreach (['<hyperlinks', '<printOptions', '<pageMargins', '<pageSetup', '<headerFooter', '<drawing', '<legacyDrawing', '<tableParts', '</worksheet>'] as $next) {
            $pos = strpos($xml, $next);
            if ($pos !== false) {
                $at = min($at, $pos);
            }
        }
        $zip->addFromString($entry, substr($xml, 0, $at) . $block . substr($xml, $at));
        $zip->close();
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letter = chr(65 + ($n - 1) % 26) . $letter;
        }

        return $letter;
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
