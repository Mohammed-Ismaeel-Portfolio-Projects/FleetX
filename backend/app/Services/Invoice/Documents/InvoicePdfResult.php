<?php

declare(strict_types=1);

namespace App\Services\Invoice\Documents;

/**
 * Rendered invoice PDF payload (Phase 6d).
 */
readonly class InvoicePdfResult
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $disposition,
    ) {}

    public function contentType(): string
    {
        return 'application/pdf';
    }
}
