<?php

declare(strict_types=1);

namespace App\Services\Invoice\Documents;

/**
 * Persisted monthly invoice projection for PDF rendering (Phase 6d).
 */
readonly class InvoiceDocumentViewModel
{
    /**
     * @param  list<InvoiceDocumentLineItemViewModel>  $lineItems
     */
    public function __construct(
        public string $audience,
        public int $invoiceId,
        public string $status,
        public string $companyName,
        public ?string $companyCode,
        public string $billingPeriodStart,
        public string $billingPeriodEnd,
        public string $currency,
        public string $subtotalFormatted,
        public string $totalFormatted,
        public ?string $issuedAtFormatted,
        public array $lineItems,
    ) {}

    public function isOps(): bool
    {
        return $this->audience === 'ops';
    }
}
