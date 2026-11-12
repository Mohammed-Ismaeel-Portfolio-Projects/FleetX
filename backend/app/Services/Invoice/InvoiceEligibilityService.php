<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\InvoiceEligibilityCode;
use App\Models\InvoiceLineItem;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Determines invoice eligibility from persisted final pricing only (Phase 6a).
 *
 * Does not recalculate pricing or mutate pricing snapshots.
 */
class InvoiceEligibilityService
{
    public function evaluate(Shipment $shipment): InvoiceEligibilityResult
    {
        $shipment->loadMissing(['finalPriceBreakdown', 'invoiceLineItem']);

        if ($shipment->customer_company_id === null) {
            return $this->result(
                InvoiceEligibilityCode::MISSING_BILLING_COMPANY,
                $shipment,
            );
        }

        if (InvoiceLineItem::query()->where('shipment_id', $shipment->id)->exists()) {
            return $this->result(
                InvoiceEligibilityCode::ALREADY_INVOICED,
                $shipment,
            );
        }

        $finalPrice = $shipment->finalPriceBreakdown;

        if ($finalPrice === null) {
            return $this->result(
                InvoiceEligibilityCode::MISSING_FINAL_PRICE,
                $shipment,
            );
        }

        $statusValue = $finalPrice->getAttributes()['status'] ?? null;

        if ($statusValue === null || trim((string) $statusValue) === '') {
            return $this->result(
                InvoiceEligibilityCode::INVALID_FINAL_PRICE_STATE,
                $shipment,
                $finalPrice,
            );
        }

        if ($statusValue !== FinalPriceBreakdownStatus::FINALIZED->value) {
            return $this->result(
                InvoiceEligibilityCode::NOT_FINALIZED,
                $shipment,
                $finalPrice,
            );
        }

        if ($finalPrice->final_total === null || trim((string) $finalPrice->final_total) === '') {
            return $this->result(
                InvoiceEligibilityCode::MISSING_FINAL_TOTAL,
                $shipment,
                $finalPrice,
            );
        }

        if ($finalPrice->currency === null || trim($finalPrice->currency) === '') {
            return $this->result(
                InvoiceEligibilityCode::MISSING_CURRENCY,
                $shipment,
                $finalPrice,
            );
        }

        return new InvoiceEligibilityResult(
            code: InvoiceEligibilityCode::ELIGIBLE,
            eligible: true,
            shipmentId: $shipment->id,
            finalPriceBreakdownId: $finalPrice->id,
            invoiceAmount: (float) $finalPrice->final_total,
            currency: $finalPrice->currency,
        );
    }

    /**
     * Query builder for shipments eligible for invoicing within a billing period.
     *
     * Billing period membership uses a half-open interval [start, end):
     * calculated_at >= billing_period_start AND calculated_at < billing_period_end.
     *
     * Example (August 2026): start = 2026-08-01, end = 2026-09-01 (exclusive).
     *
     * Uses persisted final_total and excludes already invoiced shipments.
     * Does not parse breakdown_json or recalculate pricing.
     */
    public function eligibleShipmentsQuery(
        string $companyId,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
    ): Builder {
        $periodStart = $billingPeriodStart->copy()->startOfDay();
        $periodEnd = $billingPeriodEnd->copy()->startOfDay();

        return Shipment::query()
            ->where('customer_company_id', $companyId)
            ->whereHas('finalPriceBreakdown', function (Builder $query) use ($periodStart, $periodEnd) {
                $query
                    ->where('status', FinalPriceBreakdownStatus::FINALIZED->value)
                    ->whereNotNull('final_total')
                    ->whereNotNull('currency')
                    ->where('currency', '!=', '')
                    ->where('calculated_at', '>=', $periodStart)
                    ->where('calculated_at', '<', $periodEnd);
            })
            ->whereDoesntHave('invoiceLineItem')
            ->with(['finalPriceBreakdown']);
    }

    /**
     * @return Collection<int, Shipment>
     */
    public function eligibleShipmentsForCompany(
        string $companyId,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
    ): Collection {
        return $this->eligibleShipmentsQuery($companyId, $billingPeriodStart, $billingPeriodEnd)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return Collection<int, InvoiceEligibilityResult>
     */
    public function evaluateEligibleShipmentsForCompany(
        string $companyId,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
    ): Collection {
        return $this->eligibleShipmentsForCompany($companyId, $billingPeriodStart, $billingPeriodEnd)
            ->map(fn (Shipment $shipment) => $this->evaluate($shipment));
    }

    private function result(
        InvoiceEligibilityCode $code,
        Shipment $shipment,
        ?ShipmentFinalPriceBreakdown $finalPrice = null,
    ): InvoiceEligibilityResult {
        return new InvoiceEligibilityResult(
            code: $code,
            eligible: false,
            shipmentId: $shipment->id,
            finalPriceBreakdownId: $finalPrice?->id,
            invoiceAmount: $finalPrice !== null && $finalPrice->final_total !== null
                ? (float) $finalPrice->final_total
                : null,
            currency: $finalPrice?->currency,
        );
    }
}
