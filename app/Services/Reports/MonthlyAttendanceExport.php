<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Employee;
use App\Services\Attendance\Period\PayCycle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Monthly attendance reports for many employees, as one PDF (a page each) or a ZIP with one PDF per employee.
 * Small batches are made while HR waits; big ones run in the background (BuildMonthlyAttendanceExport).
 */
final class MonthlyAttendanceExport
{
    /** Employees per export made while HR waits; above this it runs in the background. */
    public const SYNC_LIMIT = 25;

    /** Background exports are kept this long, then deleted (see prune()). */
    public const KEEP_DAYS = 7;

    public function __construct(
        private readonly MonthlyAttendanceStatement $statements,
        private readonly MonthlyAttendancePdf $pdf,
        private readonly PayCycle $cycle,
    ) {}

    /** @param Collection<int, Employee> $employees */
    public function combinedPdf(Collection $employees, int $year, int $month, ?string $printedBy): string
    {
        return $this->pdf->render($this->statements->buildMany($employees, $year, $month), $printedBy);
    }

    /**
     * One PDF per employee in a ZIP, written to a temporary file.
     *
     * @param  Collection<int, Employee>  $employees
     * @return string absolute path of the ZIP
     */
    public function zip(Collection $employees, int $year, int $month, ?string $printedBy): string
    {
        $path = tempnam(sys_get_temp_dir(), 'att') . '.zip';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP file.');
        }
        $label = $this->cycle->label($year, $month);
        $employees = $employees->values();
        foreach ($this->statements->buildMany($employees, $year, $month) as $i => $statement) {
            $zip->addFromString(self::fileName($employees[$i], $label), $this->pdf->render([$statement], $printedBy));
        }
        $zip->close();

        return $path;
    }

    /** "E001 - Ali Khan - Sep 2026.pdf", safe on every system. */
    public static function fileName(Employee $employee, string $periodLabel): string
    {
        $name = Str::of("{$employee->code} - {$employee->fullName()} - {$periodLabel}")->ascii()->replaceMatches('/[^A-Za-z0-9 ._-]+/', '')->squish();

        return "{$name}.pdf";
    }

    /** Delete background exports older than KEEP_DAYS. */
    public static function prune(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->allFiles('exports') as $file) {
            if ($disk->lastModified($file) < now()->subDays(self::KEEP_DAYS)->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
