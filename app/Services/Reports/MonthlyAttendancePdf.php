<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Support\Brand;
use Barryvdh\DomPDF\Facade\Pdf;

/** Monthly attendance report PDF: one A4 page per employee, "Page X of Y" on every page. */
final class MonthlyAttendancePdf
{
    /** @param list<array<string, mixed>> $statements from MonthlyAttendanceStatement */
    public function render(array $statements, ?string $printedBy): string
    {
        $pdf = Pdf::loadView('pdf.monthly-attendance', [
            'statements' => $statements,
            'company' => tenant('company_name'),
            'logo' => Brand::logo(),
            'printedBy' => $printedBy,
            'printedOn' => now(tenant('timezone') ?? config('hazri.default_timezone'))->format('d M Y, h:i A'),
            'app' => Brand::name(),
        ])->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true); // embed only the glyphs used: small bulk PDFs

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 28, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, [0.45, 0.45, 0.45]);

        return $dompdf->output();
    }
}
