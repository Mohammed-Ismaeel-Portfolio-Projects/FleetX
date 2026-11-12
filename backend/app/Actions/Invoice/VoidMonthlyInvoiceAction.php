<?php

namespace App\Actions\Invoice;

use App\Core\ApiResult;
use App\Services\Invoice\Exceptions\MonthlyInvoiceLifecycleException;
use App\Services\Invoice\MonthlyInvoiceLifecycleService;

/**
 * Ops monthly invoice void (Phase 6e).
 */
class VoidMonthlyInvoiceAction
{
    public function __construct(
        private readonly MonthlyInvoiceLifecycleService $lifecycleService,
    ) {}

    public function execute(int|string $monthlyInvoiceId): ApiResult
    {
        try {
            $invoice = $this->lifecycleService->void($monthlyInvoiceId);

            return ApiResult::success($invoice, 'Monthly invoice voided successfully');
        } catch (MonthlyInvoiceLifecycleException $e) {
            return ApiResult::error($e->getMessage(), $e->statusCode, $this->errorsPayload($e));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function errorsPayload(MonthlyInvoiceLifecycleException $e): ?array
    {
        if ($e->errorCode === null && $e->context === []) {
            return null;
        }

        return array_merge(
            $e->errorCode !== null ? ['code' => $e->errorCode->value] : [],
            $e->context,
        );
    }
}
