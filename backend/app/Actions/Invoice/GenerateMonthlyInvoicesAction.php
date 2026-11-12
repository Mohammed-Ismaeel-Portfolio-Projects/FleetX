<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Services\Invoice\MonthlyInvoiceGenerationResult;
use App\Services\Invoice\MonthlyInvoiceGenerationService;
use Carbon\CarbonInterface;

/**
 * Internal monthly invoice generation entry point (Phase 6b).
 */
class GenerateMonthlyInvoicesAction
{
    public function __construct(
        private readonly MonthlyInvoiceGenerationService $generationService,
    ) {}

    public function execute(
        string $companyId,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
    ): MonthlyInvoiceGenerationResult {
        return $this->generationService->generate(
            $companyId,
            $billingPeriodStart,
            $billingPeriodEnd,
        );
    }
}
