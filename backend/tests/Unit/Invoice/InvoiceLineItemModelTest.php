<?php

namespace Tests\Unit\Invoice;

use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\TestCase;

class InvoiceLineItemModelTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
    }

    public function test_invoice_line_item_belongs_to_monthly_invoice(): void
    {
        ['lineItem' => $lineItem, 'invoice' => $invoice] = $this->makeLineItemFixture();

        $this->assertTrue($lineItem->monthlyInvoice->is($invoice));
    }

    public function test_invoice_line_item_belongs_to_shipment(): void
    {
        ['lineItem' => $lineItem, 'shipment' => $shipment] = $this->makeLineItemFixture();

        $this->assertTrue($lineItem->shipment->is($shipment));
    }

    public function test_invoice_line_item_belongs_to_final_price_breakdown(): void
    {
        ['lineItem' => $lineItem, 'finalPrice' => $finalPrice] = $this->makeLineItemFixture();

        $this->assertTrue($lineItem->finalPriceBreakdown->is($finalPrice));
    }

    public function test_shipment_has_one_invoice_line_item(): void
    {
        ['lineItem' => $lineItem, 'shipment' => $shipment] = $this->makeLineItemFixture();

        $shipment->load('invoiceLineItem');

        $this->assertTrue($shipment->invoiceLineItem->is($lineItem));
    }

    public function test_final_price_breakdown_has_one_invoice_line_item(): void
    {
        ['lineItem' => $lineItem, 'finalPrice' => $finalPrice] = $this->makeLineItemFixture();

        $finalPrice->load('invoiceLineItem');

        $this->assertTrue($finalPrice->invoiceLineItem->is($lineItem));
    }

    public function test_duplicate_shipment_invoice_line_is_rejected_by_database(): void
    {
        ['shipment' => $shipment, 'invoice' => $invoice, 'finalPrice' => $finalPrice] = $this->makeLineItemFixture();

        $this->expectException(\Illuminate\Database\QueryException::class);

        InvoiceLineItem::create([
            'monthly_invoice_id' => $invoice->id,
            'shipment_id' => $shipment->id,
            'shipment_final_price_breakdown_id' => $finalPrice->id,
            'description' => 'Duplicate attempt',
            'quantity' => 1,
            'unit_price' => 50.00,
            'line_total' => 50.00,
            'currency' => 'USD',
        ]);
    }

    public function test_duplicate_final_price_breakdown_invoice_line_is_rejected_by_database(): void
    {
        ['invoice' => $invoice, 'finalPrice' => $finalPrice, 'company' => $company] = $this->makeLineItemFixture();

        $otherFixture = $this->createFinalizableShipment($this->createFullPricingPolicy());
        $otherShipment = $otherFixture['shipment'];
        $otherShipment->update(['customer_company_id' => $company->id]);
        $otherFinalPrice = $this->finalizeShipmentFixture($otherFixture);

        $this->expectException(\Illuminate\Database\QueryException::class);

        InvoiceLineItem::create([
            'monthly_invoice_id' => $invoice->id,
            'shipment_id' => $otherShipment->id,
            'shipment_final_price_breakdown_id' => $finalPrice->id,
            'description' => 'Duplicate final price',
            'quantity' => 1,
            'unit_price' => 50.00,
            'line_total' => 50.00,
            'currency' => 'USD',
        ]);
    }

    /**
     * @return array{lineItem: InvoiceLineItem, invoice: MonthlyInvoice, shipment: Shipment, finalPrice: ShipmentFinalPriceBreakdown, company: Company}
     */
    private function makeLineItemFixture(): array
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $finalPrice = $this->finalizeShipmentFixture($fixture);

        $invoice = MonthlyInvoice::create([
            'company_id' => $fixture['company']->id,
            'billing_period_start' => '2026-08-01',
            'billing_period_end' => '2026-08-31',
            'currency' => 'USD',
            'subtotal' => $finalPrice->final_total,
            'total' => $finalPrice->final_total,
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);

        $lineItem = InvoiceLineItem::create([
            'monthly_invoice_id' => $invoice->id,
            'shipment_id' => $fixture['shipment']->id,
            'shipment_final_price_breakdown_id' => $finalPrice->id,
            'description' => 'Shipment '.$fixture['shipment']->shipment_number,
            'quantity' => 1,
            'unit_price' => $finalPrice->final_total,
            'line_total' => $finalPrice->final_total,
            'currency' => 'USD',
        ]);

        return [
            'lineItem' => $lineItem,
            'invoice' => $invoice,
            'shipment' => $fixture['shipment'],
            'finalPrice' => $finalPrice,
            'company' => $fixture['company'],
        ];
    }
}
