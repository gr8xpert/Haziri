<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\Excel\Spreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** XLSX + PDF output for any Report (spec §10: every report exports to Excel; PDF where noted). */
final class ReportExporter
{
    /** @param array<string, mixed> $filters */
    public function xlsx(Report $report, array $filters): BinaryFileResponse
    {
        $columns = $report->columns($filters);
        $rows = $report->rows($filters);
        $path = tempnam(sys_get_temp_dir(), 'rpt') . '.xlsx';
        Spreadsheet::write($path, array_values($columns), array_map(
            fn (array $r) => array_map(fn (string $key) => $r[$key] ?? null, array_keys($columns)),
            $rows,
        ));

        return response()->download($path, $this->filename($report, $filters, 'xlsx'))->deleteFileAfterSend();
    }

    /** @param array<string, mixed> $filters */
    public function pdf(Report $report, array $filters): Response
    {
        $rows = $report->rows($filters);
        $html = view('pdf.report', [
            'report' => $report,
            'company' => tenant('company_name'),
            'subtitle' => $report->subtitle($filters),
            'columns' => $report->columns($filters),
            'rows' => $rows,
            'summary' => $report->summary($filters, $rows),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4', $report->paper())->download($this->filename($report, $filters, 'pdf'));
    }

    /** @param array<string, mixed> $filters */
    private function filename(Report $report, array $filters, string $ext): string
    {
        return Str::slug($report->title() . ' ' . $report->subtitle($filters)) . '.' . $ext;
    }
}
