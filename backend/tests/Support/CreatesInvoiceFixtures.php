<?php

namespace Tests\Support;

use App\Actions\Invoice\GenerateMonthlyInvoicesAction;
use App\Models\Company;
use App\Models\MonthlyInvoice;

trait CreatesInvoiceFixtures
{
    /**
     * Finalize a shipment and generate its monthly invoice for the billing period.
     *
     * @param  array<string, mixed>  $shipmentOverrides
     */
    protected function createGeneratedInvoiceForCompany(
        Company $company,
        array $shipmentOverrides = [],
    ): MonthlyInvoice {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, [], array_merge([
            'customer_company_id' => $company->id,
        ], $shipmentOverrides));
        $finalPrice = $this->finalizeShipmentFixture($fixture);
        [$periodStart, $periodEnd] = $this->billingPeriodContaining($finalPrice->calculated_at);

        $result = app(GenerateMonthlyInvoicesAction::class)->execute(
            $company->id,
            $periodStart,
            $periodEnd,
        );

        return MonthlyInvoice::query()
            ->with(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown'])
            ->findOrFail($result->invoices->first()->invoiceId);
    }
}
