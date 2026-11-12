<?php

namespace Tests\Unit\Invoice;

use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Services\Invoice\Documents\InvoiceDocumentViewModelFactory;
use App\Services\Invoice\Documents\InvoiceMoneyFormatter;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\Support\CreatesInvoiceFixtures;
use Tests\Support\DefinesBillingPeriods;
use Tests\TestCase;

class InvoiceDocumentViewModelFactoryTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use CreatesInvoiceFixtures;
    use DefinesBillingPeriods;
    use RefreshDatabase;

    private InvoiceDocumentViewModelFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->factory = app(InvoiceDocumentViewModelFactory::class);
    }

    public function test_ops_view_model_uses_persisted_invoice_amounts(): void
    {
        $invoice = $this->createGeneratedInvoiceForCompany(Company::factory()->create(), [
            'reference_number' => 'PO-DOC-001',
        ]);

        $document = $this->factory->forOps($invoice->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown']));

        $this->assertSame('ops', $document->audience);
        $this->assertSame($invoice->id, $document->invoiceId);
        $this->assertSame(
            InvoiceMoneyFormatter::format((string) $invoice->total, $invoice->currency),
            $document->totalFormatted,
        );
        $this->assertCount(1, $document->lineItems);
        $this->assertSame('PO-DOC-001', $document->lineItems[0]->shipmentReference);
        $this->assertNotNull($document->lineItems[0]->finalPriceBreakdownId);
    }

    public function test_client_view_model_excludes_internal_audit_fields(): void
    {
        $invoice = $this->createGeneratedInvoiceForCompany(Company::factory()->create());

        $document = $this->factory->forClient($invoice->fresh(['company', 'lineItems.shipment']));

        $this->assertSame('client', $document->audience);
        $this->assertNull($document->lineItems[0]->lineItemId);
        $this->assertNull($document->lineItems[0]->shipmentId);
        $this->assertNull($document->lineItems[0]->finalPriceBreakdownId);
        $this->assertNull($document->lineItems[0]->finalPriceSnapshotSummary);
    }

    public function test_null_issued_at_is_not_fabricated(): void
    {
        $invoice = $this->createGeneratedInvoiceForCompany(Company::factory()->create());
        $this->assertNull($invoice->issued_at);

        $document = $this->factory->forClient($invoice->fresh(['company', 'lineItems.shipment']));

        $this->assertNull($document->issuedAtFormatted);
    }

    public function test_empty_line_items_are_represented_without_fabrication(): void
    {
        $company = Company::factory()->create();
        $invoice = MonthlyInvoice::create([
            'company_id' => $company->id,
            'billing_period_start' => '2026-08-01',
            'billing_period_end' => '2026-09-01',
            'currency' => 'USD',
            'subtotal' => '0.00',
            'total' => '0.00',
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);

        $document = $this->factory->forOps($invoice->fresh(['company', 'lineItems.shipment', 'lineItems.finalPriceBreakdown']));

        $this->assertSame([], $document->lineItems);
        $this->assertSame('0.00 USD', $document->totalFormatted);
    }
}
