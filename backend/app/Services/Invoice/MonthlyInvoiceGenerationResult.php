<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Enums\MonthlyInvoiceGenerationStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Structured monthly invoice generation outcome (Phase 6b).
 */
readonly class MonthlyInvoiceGenerationResult
{
    /**
     * @param  Collection<int, GeneratedInvoiceSummary>  $invoices
     */
    public function __construct(
        public MonthlyInvoiceGenerationStatus $status,
        public string $companyId,
        public CarbonInterface $billingPeriodStart,
        public CarbonInterface $billingPeriodEnd,
        public Collection $invoices,
    ) {}

    public function hasInvoices(): bool
    {
        return $this->invoices->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'company_id' => $this->companyId,
            'billing_period_start' => $this->billingPeriodStart->toDateString(),
            'billing_period_end' => $this->billingPeriodEnd->toDateString(),
            'invoices' => $this->invoices
                ->map(fn (GeneratedInvoiceSummary $invoice) => $invoice->toArray())
                ->values()
                ->all(),
        ];
    }

    public static function nothingToInvoice(
        string $companyId,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
    ): self {
        return new self(
            status: MonthlyInvoiceGenerationStatus::NOTHING_TO_INVOICE,
            companyId: $companyId,
            billingPeriodStart: $billingPeriodStart,
            billingPeriodEnd: $billingPeriodEnd,
            invoices: collect(),
        );
    }
}
