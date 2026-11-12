<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Enums\MonthlyInvoiceGenerationStatus;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\Shipment;
use App\Services\Invoice\Exceptions\InvalidBillingPeriodException;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates monthly invoices from eligible finalized shipment pricing (Phase 6b).
 *
 * Billing period membership: [billing_period_start, billing_period_end) based on
 * ShipmentFinalPriceBreakdown.calculated_at.
 *
 * Does not recalculate pricing or mutate pricing snapshots.
 */
class MonthlyInvoiceGenerationService
{
    public function __construct(
        private readonly InvoiceEligibilityService $eligibilityService,
    ) {}

    public function generate(
        string $companyId,
        CarbonInterface $billingPeriodStart,
        CarbonInterface $billingPeriodEnd,
    ): MonthlyInvoiceGenerationResult {
        $periodStart = $billingPeriodStart->copy()->startOfDay();
        $periodEnd = $billingPeriodEnd->copy()->startOfDay();

        $this->validateBillingPeriod($periodStart, $periodEnd);

        $eligibleShipments = $this->eligibilityService
            ->eligibleShipmentsForCompany($companyId, $periodStart, $periodEnd)
            ->filter(function (Shipment $shipment) {
                return $this->eligibilityService->evaluate($shipment)->isEligible();
            });

        if ($eligibleShipments->isEmpty()) {
            $existingInvoices = $this->existingInvoicesForPeriod($companyId, $periodStart, $periodEnd);

            if ($existingInvoices->isNotEmpty()) {
                return new MonthlyInvoiceGenerationResult(
                    status: MonthlyInvoiceGenerationStatus::ALREADY_EXISTS,
                    companyId: $companyId,
                    billingPeriodStart: $periodStart,
                    billingPeriodEnd: $periodEnd,
                    invoices: $existingInvoices->map(
                        fn (MonthlyInvoice $invoice) => $this->summaryFromInvoice($invoice, alreadyExisted: true),
                    ),
                );
            }

            return MonthlyInvoiceGenerationResult::nothingToInvoice(
                $companyId,
                $periodStart,
                $periodEnd,
            );
        }

        /** @var Collection<string, Collection<int, Shipment>> $shipmentsByCurrency */
        $shipmentsByCurrency = $eligibleShipments->groupBy(
            fn (Shipment $shipment) => $shipment->finalPriceBreakdown->currency,
        );

        $summaries = collect();

        foreach ($shipmentsByCurrency as $currency => $shipments) {
            $summaries->push(
                $this->generateForCurrency(
                    $companyId,
                    $periodStart,
                    $periodEnd,
                    (string) $currency,
                    $shipments,
                ),
            );
        }

        $allExisting = $summaries->isNotEmpty()
            && $summaries->every(fn (GeneratedInvoiceSummary $summary) => $summary->alreadyExisted);

        $status = $allExisting
            ? MonthlyInvoiceGenerationStatus::ALREADY_EXISTS
            : MonthlyInvoiceGenerationStatus::GENERATED;

        if ($summaries->isEmpty()) {
            $status = MonthlyInvoiceGenerationStatus::NOTHING_TO_INVOICE;
        }

        return new MonthlyInvoiceGenerationResult(
            status: $status,
            companyId: $companyId,
            billingPeriodStart: $periodStart,
            billingPeriodEnd: $periodEnd,
            invoices: $summaries,
        );
    }

    /**
     * @param  Collection<int, Shipment>  $shipments
     */
    private function generateForCurrency(
        string $companyId,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        string $currency,
        Collection $shipments,
    ): GeneratedInvoiceSummary {
        $existing = MonthlyInvoice::findForBillingIdentity(
            $companyId,
            $periodStart,
            $periodEnd,
            $currency,
        );

        if ($existing !== null) {
            return $this->summaryFromInvoice($existing, alreadyExisted: true);
        }

        return DB::transaction(function () use ($companyId, $periodStart, $periodEnd, $currency, $shipments) {
            $lockedExisting = MonthlyInvoice::query()
                ->where('company_id', $companyId)
                ->whereDate('billing_period_start', $periodStart->toDateString())
                ->whereDate('billing_period_end', $periodEnd->toDateString())
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if ($lockedExisting !== null) {
                return $this->summaryFromInvoice($lockedExisting, alreadyExisted: true);
            }

            Shipment::query()
                ->whereIn('id', $shipments->pluck('id'))
                ->lockForUpdate()
                ->get();

            $invoiceShipments = $shipments
                ->filter(fn (Shipment $shipment) => ! $shipment->invoiceLineItem()->exists())
                ->filter(fn (Shipment $shipment) => $this->eligibilityService->evaluate($shipment)->isEligible())
                ->values();

            if ($invoiceShipments->isEmpty()) {
                $existingAfterLock = MonthlyInvoice::findForBillingIdentity(
                    $companyId,
                    $periodStart,
                    $periodEnd,
                    $currency,
                );

                if ($existingAfterLock !== null) {
                    return $this->summaryFromInvoice($existingAfterLock, alreadyExisted: true);
                }

                throw new \RuntimeException('Eligible shipments were invoiced concurrently before invoice creation.');
            }

            try {
                $invoice = MonthlyInvoice::create([
                    'company_id' => $companyId,
                    'billing_period_start' => $periodStart->toDateString(),
                    'billing_period_end' => $periodEnd->toDateString(),
                    'currency' => $currency,
                    'subtotal' => '0.00',
                    'total' => '0.00',
                    'status' => MonthlyInvoiceStatus::DRAFT,
                    'issued_at' => null,
                ]);
            } catch (QueryException $e) {
                if ($this->isMonthlyInvoiceIdentityViolation($e)) {
                    $existing = MonthlyInvoice::findForBillingIdentity(
                        $companyId,
                        $periodStart,
                        $periodEnd,
                        $currency,
                    );

                    if ($existing !== null) {
                        return $this->summaryFromInvoice($existing, alreadyExisted: true);
                    }
                }

                throw $e;
            }

            $lineTotals = [];

            foreach ($invoiceShipments as $shipment) {
                $shipment->loadMissing('finalPriceBreakdown');
                $finalPrice = $shipment->finalPriceBreakdown;
                $lineTotal = $this->formatMoney((string) $finalPrice->final_total);

                InvoiceLineItem::create([
                    'monthly_invoice_id' => $invoice->id,
                    'shipment_id' => $shipment->id,
                    'shipment_final_price_breakdown_id' => $finalPrice->id,
                    'description' => $this->lineItemDescription($shipment),
                    'quantity' => 1,
                    'unit_price' => $lineTotal,
                    'line_total' => $lineTotal,
                    'currency' => $finalPrice->currency,
                ]);

                $lineTotals[] = $lineTotal;
            }

            $subtotal = $this->sumMoney($lineTotals);

            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            return $this->summaryFromInvoice($invoice->fresh(['lineItems']), alreadyExisted: false);
        });
    }

    /**
     * @return Collection<int, MonthlyInvoice>
     */
    private function existingInvoicesForPeriod(
        string $companyId,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
    ): Collection {
        return MonthlyInvoice::query()
            ->where('company_id', $companyId)
            ->whereDate('billing_period_start', $periodStart->toDateString())
            ->whereDate('billing_period_end', $periodEnd->toDateString())
            ->orderBy('currency')
            ->get();
    }

    private function validateBillingPeriod(CarbonInterface $start, CarbonInterface $end): void
    {
        if ($start->greaterThanOrEqualTo($end)) {
            throw new InvalidBillingPeriodException(
                'Billing period end must be after billing period start.',
            );
        }
    }

    private function lineItemDescription(Shipment $shipment): string
    {
        $reference = filled($shipment->reference_number)
            ? $shipment->reference_number
            : $shipment->shipment_number;

        return 'Shipment '.$reference;
    }

    private function summaryFromInvoice(MonthlyInvoice $invoice, bool $alreadyExisted): GeneratedInvoiceSummary
    {
        $invoice->loadMissing('lineItems');
        $lineItemCount = $invoice->lineItems->count();

        return new GeneratedInvoiceSummary(
            invoiceId: $invoice->id,
            currency: $invoice->currency,
            lineItemCount: $lineItemCount,
            subtotal: $this->formatMoney((string) $invoice->subtotal),
            total: $this->formatMoney((string) $invoice->total),
            alreadyExisted: $alreadyExisted,
        );
    }

    /**
     * @param  list<string>  $amounts
     */
    private function sumMoney(array $amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = bcadd($total, $this->formatMoney($amount), 2);
        }

        return $total;
    }

    private function formatMoney(string $amount): string
    {
        return bcadd($amount, '0', 2);
    }

    private function isMonthlyInvoiceIdentityViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'monthly_invoices_company_period_currency_unique')
            || (str_contains($message, 'duplicate key') && str_contains($message, 'monthly_invoices'));
    }

    private function isShipmentLineItemViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'invoice_line_items_shipment_id_unique')
            || (str_contains($message, 'invoice_line_items') && str_contains($message, 'shipment_id'));
    }

    private function isFinalPriceLineItemViolation(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'invoice_line_items_final_price_unique')
            || (str_contains($message, 'invoice_line_items') && str_contains($message, 'shipment_final_price_breakdown_id'));
    }
}
