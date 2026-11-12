<?php

namespace Tests\Unit\Invoice;

use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\TestCase;

class MonthlyInvoiceModelTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);
    }

    public function test_monthly_invoice_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $invoice = $this->makeMonthlyInvoice($company);

        $this->assertTrue($invoice->company->is($company));
    }

    public function test_company_has_many_monthly_invoices(): void
    {
        $company = Company::factory()->create();
        $this->makeMonthlyInvoice($company);
        $this->makeMonthlyInvoice($company, ['billing_period_start' => '2026-07-01', 'billing_period_end' => '2026-07-31']);

        $company->load('monthlyInvoices');

        $this->assertCount(2, $company->monthlyInvoices);
    }

    public function test_monthly_invoice_has_many_line_items(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $finalPrice = $this->finalizeShipmentFixture($fixture);
        $invoice = $this->makeMonthlyInvoice($fixture['company']);

        InvoiceLineItem::create([
            'monthly_invoice_id' => $invoice->id,
            'shipment_id' => $fixture['shipment']->id,
            'shipment_final_price_breakdown_id' => $finalPrice->id,
            'description' => 'Shipment freight',
            'quantity' => 1,
            'unit_price' => $finalPrice->final_total,
            'line_total' => $finalPrice->final_total,
            'currency' => 'USD',
        ]);

        $invoice->load('lineItems');

        $this->assertCount(1, $invoice->lineItems);
    }

    public function test_status_is_cast_to_monthly_invoice_status_enum(): void
    {
        $invoice = $this->makeMonthlyInvoice(Company::factory()->create(), [
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);

        $this->assertInstanceOf(MonthlyInvoiceStatus::class, $invoice->status);
        $this->assertSame(MonthlyInvoiceStatus::DRAFT, $invoice->status);
    }

    public function test_billing_period_dates_are_cast(): void
    {
        $invoice = $this->makeMonthlyInvoice(Company::factory()->create());

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invoice->billing_period_start);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $invoice->billing_period_end);
    }

    public function test_finalized_to_paid_transition_is_allowed(): void
    {
        $invoice = $this->makeMonthlyInvoice(Company::factory()->create(), [
            'status' => MonthlyInvoiceStatus::FINALIZED,
            'issued_at' => now(),
        ]);

        $invoice->status = MonthlyInvoiceStatus::PAID;
        $invoice->paid_at = now();
        $invoice->stripe_checkout_session_id = 'cs_test_123';
        $invoice->save();

        $this->assertSame(MonthlyInvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertTrue($invoice->fresh()->isBillingImmutable());
    }

    public function test_paid_invoice_is_immutable(): void
    {
        $invoice = $this->makeMonthlyInvoice(Company::factory()->create(), [
            'status' => MonthlyInvoiceStatus::FINALIZED,
            'issued_at' => now(),
        ]);

        $invoice->status = MonthlyInvoiceStatus::PAID;
        $invoice->paid_at = now();
        $invoice->save();

        $this->expectException(\App\Services\Invoice\Exceptions\MonthlyInvoiceLifecycleException::class);
        $invoice->total = 9999.99;
        $invoice->save();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMonthlyInvoice(Company $company, array $overrides = []): MonthlyInvoice
    {
        return MonthlyInvoice::create(array_merge([
            'company_id' => $company->id,
            'billing_period_start' => '2026-08-01',
            'billing_period_end' => '2026-08-31',
            'currency' => 'USD',
            'subtotal' => 0,
            'total' => 0,
            'status' => MonthlyInvoiceStatus::DRAFT,
            'issued_at' => null,
        ], $overrides));
    }
}
