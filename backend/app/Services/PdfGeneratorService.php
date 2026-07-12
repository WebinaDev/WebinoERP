<?php

namespace App\Services;

/**
 * Wraps barryvdh/laravel-dompdf when installed and ext-dom is available.
 */
class PdfGeneratorService
{
    public function htmlToPdf(string $html): ?string
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return null;
        }

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);

            return $pdf->output();
        } catch (\Throwable) {
            return null;
        }
    }
}
