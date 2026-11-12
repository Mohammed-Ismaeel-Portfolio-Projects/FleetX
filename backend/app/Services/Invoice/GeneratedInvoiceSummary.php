<?php

declare(strict_types=1);

namespace App\Services\Invoice;

/**
 * Summary of a generated or existing monthly invoice (Phase 6b).
 */
readonly class GeneratedInvoiceSummary
{
    public function __construct(
        public int $invoiceId,
        public string $currency,
        public int $lineItemCount,
        public string $subtotal,
        public string $total,
        public bool $alreadyExisted,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'currency' => $this->currency,
            'line_item_count' => $this->lineItemCount,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'already_existed' => $this->alreadyExisted,
        ];
    }
}
