<?php

declare(strict_types=1);

namespace App\Services\Invoice\Documents;

/**
 * Persisted invoice line item projection for PDF rendering (Phase 6d).
 */
readonly class InvoiceDocumentLineItemViewModel
{
    public function __construct(
        public string $description,
        public int $quantity,
        public string $unitPriceFormatted,
        public string $lineTotalFormatted,
        public string $currency,
        public ?string $shipmentReference = null,
        public ?int $lineItemId = null,
        public ?string $shipmentId = null,
        public ?int $finalPriceBreakdownId = null,
        public ?string $finalPriceSnapshotSummary = null,
    ) {}
}
