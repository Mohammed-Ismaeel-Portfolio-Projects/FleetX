<?php

namespace App\Support;

use App\Services\Invoice\Documents\InvoicePdfResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP response helper for streamed invoice PDFs (Phase 6d).
 */
final class InvoicePdfResponse
{
    public static function fromResult(InvoicePdfResult $pdf): Response
    {
        return response($pdf->content, 200, [
            'Content-Type' => $pdf->contentType(),
            'Content-Disposition' => sprintf('%s; filename="%s"', $pdf->disposition, $pdf->filename),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
