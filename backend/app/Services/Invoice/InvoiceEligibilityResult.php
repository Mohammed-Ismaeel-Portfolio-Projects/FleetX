<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Enums\InvoiceEligibilityCode;

/**
 * Structured invoice eligibility outcome (Phase 6a).
 */
readonly class InvoiceEligibilityResult
{
    public function __construct(
        public InvoiceEligibilityCode $code,
        public bool $eligible,
        public ?string $shipmentId = null,
        public ?int $finalPriceBreakdownId = null,
        public ?float $invoiceAmount = null,
        public ?string $currency = null,
    ) {}

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'eligible' => $this->eligible,
            'shipment_id' => $this->shipmentId,
            'final_price_breakdown_id' => $this->finalPriceBreakdownId,
            'invoice_amount' => $this->invoiceAmount,
            'currency' => $this->currency,
        ];
    }
}
