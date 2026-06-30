<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Laravel\Pdf;

use LauLamanApps\DocumentSigner\Sdk\Exception\DocumentSignerException;
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Renders HTML to PDF using spatie/laravel-pdf.
 *
 * The package itself wraps Browsershot under the hood, but provides a Laravel-
 * native fluent API and respects bindings/macros registered against the
 * {@see \Spatie\LaravelPdf\Facades\Pdf} facade — making this renderer the right
 * choice when an application already configures laravel-pdf elsewhere
 * (custom Node binary, headers/footers, paper size defaults, etc.).
 */
final class LaravelPdfRenderer implements PdfRenderer
{
    /**
     * @param \Closure(PdfBuilder):void|null $configure Optional hook for fluent customisation
     *                                                   of the PdfBuilder before the PDF is produced.
     */
    public function __construct(
        private readonly ?\Closure $configure = null,
    ) {}

    public function render(string $html): string
    {
        try {
            $pdf = Pdf::html($html);

            if ($this->configure !== null) {
                ($this->configure)($pdf);
            }

            $base64 = $pdf->base64();
            $binary = base64_decode($base64, strict: true);

            if ($binary === false || $binary === '') {
                throw new DocumentSignerException('spatie/laravel-pdf returned empty PDF bytes.');
            }

            return $binary;
        } catch (DocumentSignerException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DocumentSignerException(
                'spatie/laravel-pdf failed to render HTML to PDF: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
