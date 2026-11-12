<?php

namespace Tests\Unit\Invoice;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\InvoiceEligibilityCode;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentOperationalActuals;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\ShipmentPriceVariance;
use App\Services\Invoice\InvoiceEligibilityService;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\ShipmentFinalPricingService;
use App\Services\Pricing\ShipmentOrderPricingService;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\TestCase;

/**
 * Phase 6a — Invoice eligibility foundation.
 */
class InvoiceEligibilityServiceTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use RefreshDatabase;

    private InvoiceEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->service = app(InvoiceEligibilityService::class);
    }

    public function test_finalized_shipment_is_eligible(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        $result = $this->service->evaluate($fixture['shipment']->fresh(['finalPriceBreakdown']));

        $this->assertTrue($result->isEligible());
        $this->assertSame(InvoiceEligibilityCode::ELIGIBLE, $result->code);
        $this->assertNotNull($result->invoiceAmount);
        $this->assertSame('USD', $result->currency);
        $this->assertSame(
            (float) $fixture['shipment']->fresh()->finalPriceBreakdown->final_total,
            $result->invoiceAmount,
        );
    }

    public function test_completed_shipment_without_final_price_is_not_eligible(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);

        $result = $this->service->evaluate($fixture['shipment']);

        $this->assertFalse($result->isEligible());
        $this->assertSame(InvoiceEligibilityCode::MISSING_FINAL_PRICE, $result->code);
    }

    public function test_non_finalized_final_price_is_not_eligible(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        DB::table('shipment_final_price_breakdowns')
            ->where('shipment_id', $fixture['shipment']->id)
            ->update(['status' => 'pending']);

        $result = $this->service->evaluate($fixture['shipment']->fresh(['finalPriceBreakdown']));

        $this->assertFalse($result->isEligible());
        $this->assertSame(InvoiceEligibilityCode::NOT_FINALIZED, $result->code);
    }

    public function test_missing_final_total_is_not_eligible(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        $shipment = $fixture['shipment']->fresh(['finalPriceBreakdown']);
        $shipment->finalPriceBreakdown->forceFill(['final_total' => null]);

        $result = $this->service->evaluate($shipment);

        $this->assertFalse($result->isEligible());
        $this->assertSame(InvoiceEligibilityCode::MISSING_FINAL_TOTAL, $result->code);
    }

    public function test_missing_currency_is_not_eligible(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $breakdown = $this->finalizeShipmentFixture($fixture);

        DB::table('shipment_final_price_breakdowns')
            ->where('id', $breakdown->id)
            ->update(['currency' => '']);

        $result = $this->service->evaluate($fixture['shipment']->fresh(['finalPriceBreakdown']));

        $this->assertFalse($result->isEligible());
        $this->assertSame(InvoiceEligibilityCode::MISSING_CURRENCY, $result->code);
    }

    public function test_missing_billing_company_is_not_eligible(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        DB::table('shipments')
            ->where('id', $fixture['shipment']->id)
            ->update(['customer_company_id' => null]);

        $result = $this->service->evaluate($fixture['shipment']->fresh(['finalPriceBreakdown']));

        $this->assertFalse($result->isEligible());
        $this->assertSame(InvoiceEligibilityCode::MISSING_BILLING_COMPANY, $result->code);
    }

    public function test_already_invoiced_shipment_is_not_eligible(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $finalPrice = $this->finalizeShipmentFixture($fixture);

        $invoice = MonthlyInvoice::create([
            'company_id' => $fixture['company']->id,
            'billing_period_start' => now()->startOfMonth()->toDateString(),
            'billing_period_end' => now()->startOfMonth()->addMonth()->toDateString(),
            'currency' => 'USD',
            'subtotal' => $finalPrice->final_total,
            'total' => $finalPrice->final_total,
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);

        InvoiceLineItem::create([
            'monthly_invoice_id' => $invoice->id,
            'shipment_id' => $fixture['shipment']->id,
            'shipment_final_price_breakdown_id' => $finalPrice->id,
            'description' => 'Existing line',
            'quantity' => 1,
            'unit_price' => $finalPrice->final_total,
            'line_total' => $finalPrice->final_total,
            'currency' => 'USD',
        ]);

        $result = $this->service->evaluate($fixture['shipment']->fresh(['finalPriceBreakdown', 'invoiceLineItem']));

        $this->assertFalse($result->isEligible());
        $this->assertSame(InvoiceEligibilityCode::ALREADY_INVOICED, $result->code);
    }

    public function test_invalid_final_price_state_is_not_eligible(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        DB::table('shipment_final_price_breakdowns')
            ->where('shipment_id', $fixture['shipment']->id)
            ->update(['status' => '']);

        $result = $this->service->evaluate($fixture['shipment']->fresh(['finalPriceBreakdown']));

        $this->assertFalse($result->isEligible());
        $this->assertSame(InvoiceEligibilityCode::INVALID_FINAL_PRICE_STATE, $result->code);
    }

    public function test_eligibility_does_not_invoke_pricing_services(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        $pricingEngine = Mockery::mock(PricingEngine::class);
        $pricingEngine->shouldNotReceive('calculate');
        $this->app->instance(PricingEngine::class, $pricingEngine);

        $finalPricing = Mockery::mock(ShipmentFinalPricingService::class);
        $finalPricing->shouldNotReceive('finalize');
        $this->app->instance(ShipmentFinalPricingService::class, $finalPricing);

        $policyResolver = Mockery::mock(PricingPolicyResolver::class);
        $policyResolver->shouldNotReceive('resolve');
        $this->app->instance(PricingPolicyResolver::class, $policyResolver);

        $orderPricing = Mockery::mock(ShipmentOrderPricingService::class);
        $orderPricing->shouldNotReceive('calculateAndPersistSnapshot');
        $this->app->instance(ShipmentOrderPricingService::class, $orderPricing);

        $service = app(InvoiceEligibilityService::class);
        $result = $service->evaluate($fixture['shipment']->fresh(['finalPriceBreakdown']));

        $this->assertTrue($result->isEligible());
    }

    public function test_eligible_shipments_query_returns_finalized_uninvoiced_shipments_in_period(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $finalPrice = $this->finalizeShipmentFixture($fixture);

        $calculatedAt = $finalPrice->calculated_at;
        $periodStart = $calculatedAt->copy()->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonth();

        $eligible = $this->service->eligibleShipmentsForCompany(
            $fixture['company']->id,
            $periodStart,
            $periodEnd,
        );

        $this->assertCount(1, $eligible);
        $this->assertTrue($eligible->first()->is($fixture['shipment']));
        $this->assertNotNull($eligible->first()->finalPriceBreakdown);
    }

    public function test_eligible_shipments_query_excludes_already_invoiced(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $finalPrice = $this->finalizeShipmentFixture($fixture);

        $periodStart = $finalPrice->calculated_at->copy()->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonth();

        $invoice = MonthlyInvoice::create([
            'company_id' => $fixture['company']->id,
            'billing_period_start' => $periodStart,
            'billing_period_end' => $periodEnd,
            'currency' => 'USD',
            'subtotal' => $finalPrice->final_total,
            'total' => $finalPrice->final_total,
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);

        InvoiceLineItem::create([
            'monthly_invoice_id' => $invoice->id,
            'shipment_id' => $fixture['shipment']->id,
            'shipment_final_price_breakdown_id' => $finalPrice->id,
            'description' => 'Invoiced',
            'quantity' => 1,
            'unit_price' => $finalPrice->final_total,
            'line_total' => $finalPrice->final_total,
            'currency' => 'USD',
        ]);

        $eligible = $this->service->eligibleShipmentsForCompany(
            $fixture['company']->id,
            $periodStart,
            $periodEnd,
        );

        $this->assertCount(0, $eligible);
    }

    public function test_eligible_shipments_query_excludes_other_companies(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $finalPrice = $this->finalizeShipmentFixture($fixture);

        $otherCompany = Company::factory()->create();
        $periodStart = $finalPrice->calculated_at->copy()->startOfMonth();
        $periodEnd = $periodStart->copy()->addMonth();

        $eligible = $this->service->eligibleShipmentsForCompany(
            $otherCompany->id,
            $periodStart,
            $periodEnd,
        );

        $this->assertCount(0, $eligible);
    }

    public function test_invoice_foundation_does_not_mutate_pricing_snapshots(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $finalPrice = $this->finalizeShipmentFixture($fixture);

        $snapshotsBefore = [
            'final' => $finalPrice->fresh()->getAttributes(),
            'submission' => $fixture['snapshot']->fresh()->getAttributes(),
            'actuals' => $fixture['actuals']->fresh()->getAttributes(),
            'variances' => ShipmentPriceVariance::where('shipment_final_price_breakdown_id', $finalPrice->id)
                ->get()
                ->map(fn ($v) => $v->getAttributes())
                ->all(),
        ];

        $this->service->evaluate($fixture['shipment']->fresh(['finalPriceBreakdown']));

        $invoice = MonthlyInvoice::create([
            'company_id' => $fixture['company']->id,
            'billing_period_start' => now()->startOfMonth()->toDateString(),
            'billing_period_end' => now()->startOfMonth()->addMonth()->toDateString(),
            'currency' => 'USD',
            'subtotal' => $finalPrice->final_total,
            'total' => $finalPrice->final_total,
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);

        InvoiceLineItem::create([
            'monthly_invoice_id' => $invoice->id,
            'shipment_id' => $fixture['shipment']->id,
            'shipment_final_price_breakdown_id' => $finalPrice->id,
            'description' => 'Line',
            'quantity' => 1,
            'unit_price' => $finalPrice->final_total,
            'line_total' => $finalPrice->final_total,
            'currency' => 'USD',
        ]);

        $this->assertSame($snapshotsBefore['final'], $finalPrice->fresh()->getAttributes());
        $this->assertSame($snapshotsBefore['submission'], $fixture['snapshot']->fresh()->getAttributes());
        $this->assertSame($snapshotsBefore['actuals'], $fixture['actuals']->fresh()->getAttributes());
        $this->assertSame(
            $snapshotsBefore['variances'],
            ShipmentPriceVariance::where('shipment_final_price_breakdown_id', $finalPrice->id)
                ->get()
                ->map(fn ($v) => $v->getAttributes())
                ->all(),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
