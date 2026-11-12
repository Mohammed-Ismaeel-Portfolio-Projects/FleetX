<?php

namespace Tests\Unit\Invoice;

use App\Actions\Invoice\GenerateMonthlyInvoicesAction;
use App\Enums\MonthlyInvoiceGenerationStatus;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentOperationalActuals;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Models\ShipmentPriceVariance;
use App\Services\Invoice\Exceptions\InvalidBillingPeriodException;
use App\Services\Invoice\MonthlyInvoiceGenerationService;
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
use Tests\Support\DefinesBillingPeriods;
use Tests\TestCase;

/**
 * Phase 6b — Monthly invoice aggregation and line item generation.
 */
class MonthlyInvoiceGenerationServiceTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use DefinesBillingPeriods;
    use RefreshDatabase;

    private MonthlyInvoiceGenerationService $service;

    private GenerateMonthlyInvoicesAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->service = app(MonthlyInvoiceGenerationService::class);
        $this->action = app(GenerateMonthlyInvoicesAction::class);
    }

    public function test_eligible_shipment_generates_invoice_with_one_line_item(): void
    {
        ['fixture' => $fixture, 'finalPrice' => $finalPrice, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture();

        $result = $this->service->generate($fixture['company']->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::GENERATED, $result->status);
        $this->assertCount(1, $result->invoices);

        $summary = $result->invoices->first();
        $invoice = MonthlyInvoice::with('lineItems')->findOrFail($summary->invoiceId);

        $this->assertSame(MonthlyInvoiceStatus::DRAFT, $invoice->status);
        $this->assertNull($invoice->issued_at);
        $this->assertCount(1, $invoice->lineItems);

        $lineItem = $invoice->lineItems->first();
        $this->assertSame(1, $lineItem->quantity);
        $this->assertSame($this->money($finalPrice->final_total), $this->money($lineItem->unit_price));
        $this->assertSame($this->money($finalPrice->final_total), $this->money($lineItem->line_total));
        $this->assertSame($this->money($finalPrice->final_total), $this->money($invoice->subtotal));
        $this->assertSame($this->money($invoice->subtotal), $this->money($invoice->total));
        $this->assertSame($finalPrice->id, $lineItem->shipment_final_price_breakdown_id);
    }

    public function test_line_item_description_uses_reference_number_when_present(): void
    {
        ['fixture' => $fixture, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture([
            'reference_number' => 'PO-7788',
        ]);

        $this->service->generate($fixture['company']->id, $start, $end);

        $lineItem = InvoiceLineItem::firstOrFail();
        $this->assertSame('Shipment PO-7788', $lineItem->description);
    }

    public function test_line_item_description_falls_back_to_shipment_number(): void
    {
        ['fixture' => $fixture, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture([
            'reference_number' => null,
        ]);

        $this->service->generate($fixture['company']->id, $start, $end);

        $lineItem = InvoiceLineItem::firstOrFail();
        $this->assertSame('Shipment '.$fixture['shipment']->shipment_number, $lineItem->description);
    }

    public function test_multiple_eligible_shipments_create_multiple_line_items_with_exact_total(): void
    {
        $policy = $this->createFullPricingPolicy();
        $first = $this->prepareEligibleFixture([], $policy);
        $secondFixture = $this->createFinalizableShipment($policy, [], [
            'customer_company_id' => $first['fixture']['company']->id,
        ]);
        $secondFinal = $this->finalizeShipmentFixture($secondFixture);

        DB::table('shipment_final_price_breakdowns')->where('id', $first['finalPrice']->id)->update(['final_total' => '100.50']);
        DB::table('shipment_final_price_breakdowns')->where('id', $secondFinal->id)->update(['final_total' => '250.25']);

        $result = $this->service->generate(
            $first['fixture']['company']->id,
            $first['periodStart'],
            $first['periodEnd'],
        );

        $invoice = MonthlyInvoice::with('lineItems')->findOrFail($result->invoices->first()->invoiceId);
        $this->assertCount(2, $invoice->lineItems);
        $this->assertSame('350.75', $this->money($invoice->total));
        $this->assertSame('350.75', $this->money($invoice->subtotal));
    }

    public function test_usd_and_eur_shipments_create_separate_invoices(): void
    {
        $policy = $this->createFullPricingPolicy();
        $usd = $this->prepareEligibleFixture([], $policy);
        $eurFixture = $this->createFinalizableShipment($policy, [], [
            'customer_company_id' => $usd['fixture']['company']->id,
        ]);
        $eurFinal = $this->finalizeShipmentFixture($eurFixture);

        DB::table('shipment_final_price_breakdowns')
            ->where('id', $eurFinal->id)
            ->update(['currency' => 'EUR', 'final_total' => '500.00']);

        $result = $this->service->generate(
            $usd['fixture']['company']->id,
            $usd['periodStart'],
            $usd['periodEnd'],
        );

        $this->assertCount(2, $result->invoices);

        $currencies = $result->invoices->pluck('currency')->sort()->values()->all();
        $this->assertSame(['EUR', 'USD'], $currencies);

        $usdInvoice = MonthlyInvoice::where('currency', 'USD')->firstOrFail();
        $eurInvoice = MonthlyInvoice::where('currency', 'EUR')->firstOrFail();

        $this->assertSame(1, $usdInvoice->lineItems()->count());
        $this->assertSame(1, $eurInvoice->lineItems()->count());
        $this->assertNotSame($usdInvoice->id, $eurInvoice->id);
    }

    public function test_empty_period_returns_structured_nothing_to_invoice_result(): void
    {
        $company = Company::factory()->create();
        [$start, $end] = $this->billingPeriodContaining(now());

        $result = $this->service->generate($company->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::NOTHING_TO_INVOICE, $result->status);
        $this->assertFalse($result->hasInvoices());
        $this->assertSame(0, MonthlyInvoice::count());
    }

    public function test_non_finalized_shipment_is_excluded_from_generation(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $this->finalizeShipmentFixture($fixture);

        DB::table('shipment_final_price_breakdowns')
            ->where('shipment_id', $fixture['shipment']->id)
            ->update(['status' => 'pending']);

        [$start, $end] = $this->billingPeriodContaining(now());

        $result = $this->service->generate($fixture['company']->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::NOTHING_TO_INVOICE, $result->status);
        $this->assertSame(0, MonthlyInvoice::count());
    }

    public function test_missing_final_price_is_excluded_from_generation(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        [$start, $end] = $this->billingPeriodContaining(now());

        $result = $this->service->generate($fixture['company']->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::NOTHING_TO_INVOICE, $result->status);
    }

    public function test_already_invoiced_shipment_is_excluded_from_new_generation(): void
    {
        ['fixture' => $fixture, 'finalPrice' => $finalPrice, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture();

        $this->service->generate($fixture['company']->id, $start, $end);

        $second = $this->createFinalizableShipment($this->createFullPricingPolicy(), [], [
            'customer_company_id' => $fixture['company']->id,
        ]);
        $this->finalizeShipmentFixture($second);

        $result = $this->service->generate($fixture['company']->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::ALREADY_EXISTS, $result->status);
        $this->assertSame(1, MonthlyInvoice::count());
        $this->assertSame(1, InvoiceLineItem::count());
        $this->assertNull(InvoiceLineItem::where('shipment_id', $second['shipment']->id)->first());
    }

    public function test_same_generation_request_twice_is_idempotent(): void
    {
        ['fixture' => $fixture, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture();

        $first = $this->service->generate($fixture['company']->id, $start, $end);
        $second = $this->service->generate($fixture['company']->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::GENERATED, $first->status);
        $this->assertSame(MonthlyInvoiceGenerationStatus::ALREADY_EXISTS, $second->status);
        $this->assertSame(
            $first->invoices->first()->invoiceId,
            $second->invoices->first()->invoiceId,
        );
        $this->assertSame(1, MonthlyInvoice::count());
        $this->assertSame(1, InvoiceLineItem::count());
    }

    public function test_action_delegates_to_generation_service(): void
    {
        ['fixture' => $fixture, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture();

        $result = $this->action->execute($fixture['company']->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::GENERATED, $result->status);
        $this->assertCount(1, $result->invoices);
    }

    public function test_duplicate_company_period_currency_invoice_is_rejected_by_database(): void
    {
        ['fixture' => $fixture, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture();

        MonthlyInvoice::create([
            'company_id' => $fixture['company']->id,
            'billing_period_start' => $start->toDateString(),
            'billing_period_end' => $end->toDateString(),
            'currency' => 'USD',
            'subtotal' => '0.00',
            'total' => '0.00',
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MonthlyInvoice::create([
            'company_id' => $fixture['company']->id,
            'billing_period_start' => $start->toDateString(),
            'billing_period_end' => $end->toDateString(),
            'currency' => 'USD',
            'subtotal' => '0.00',
            'total' => '0.00',
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);
    }

    public function test_invalid_billing_period_is_rejected(): void
    {
        $company = Company::factory()->create();
        $start = now()->startOfMonth();
        $end = $start->copy();

        $this->expectException(InvalidBillingPeriodException::class);

        $this->service->generate($company->id, $start, $end);
    }

    public function test_billing_period_uses_half_open_interval(): void
    {
        $policy = $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy);
        $finalPrice = $this->finalizeShipmentFixture($fixture);

        [$start, $end] = $this->billingPeriodContaining($finalPrice->calculated_at);

        DB::table('shipment_final_price_breakdowns')
            ->where('id', $finalPrice->id)
            ->update(['calculated_at' => $end->copy()]);

        $result = $this->service->generate($fixture['company']->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::NOTHING_TO_INVOICE, $result->status);
    }

    public function test_generation_does_not_invoke_pricing_services(): void
    {
        ['fixture' => $fixture, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture();

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

        app(MonthlyInvoiceGenerationService::class)->generate($fixture['company']->id, $start, $end);

        $this->assertSame(1, MonthlyInvoice::count());
    }

    public function test_generation_does_not_mutate_pricing_snapshots(): void
    {
        ['fixture' => $fixture, 'finalPrice' => $finalPrice, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture();

        $snapshotsBefore = [
            'final' => $finalPrice->fresh()->getAttributes(),
            'submission' => $fixture['snapshot']->fresh()->getAttributes(),
            'actuals' => $fixture['actuals']->fresh()->getAttributes(),
            'variances' => ShipmentPriceVariance::where('shipment_final_price_breakdown_id', $finalPrice->id)
                ->get()
                ->map(fn ($v) => $v->getAttributes())
                ->all(),
        ];

        $this->service->generate($fixture['company']->id, $start, $end);

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

    public function test_concurrent_generation_returns_existing_invoice_without_duplicates(): void
    {
        ['fixture' => $fixture, 'periodStart' => $start, 'periodEnd' => $end] = $this->prepareEligibleFixture();

        $existing = MonthlyInvoice::create([
            'company_id' => $fixture['company']->id,
            'billing_period_start' => $start->toDateString(),
            'billing_period_end' => $end->toDateString(),
            'currency' => 'USD',
            'subtotal' => '0.00',
            'total' => '0.00',
            'status' => MonthlyInvoiceStatus::DRAFT,
        ]);

        $result = $this->service->generate($fixture['company']->id, $start, $end);

        $this->assertSame(MonthlyInvoiceGenerationStatus::ALREADY_EXISTS, $result->status);
        $this->assertSame($existing->id, $result->invoices->first()->invoiceId);
        $this->assertSame(1, MonthlyInvoice::count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $shipmentOverrides
     * @return array{fixture: array<string, mixed>, finalPrice: ShipmentFinalPriceBreakdown, periodStart: \Carbon\CarbonInterface, periodEnd: \Carbon\CarbonInterface}
     */
    private function prepareEligibleFixture(array $shipmentOverrides = [], ?\App\Models\PricingPolicy $policy = null): array
    {
        $policy ??= $this->createFullPricingPolicy();
        $fixture = $this->createFinalizableShipment($policy, [], $shipmentOverrides);
        $finalPrice = $this->finalizeShipmentFixture($fixture);
        [$periodStart, $periodEnd] = $this->billingPeriodContaining($finalPrice->calculated_at);

        return compact('fixture', 'finalPrice', 'periodStart', 'periodEnd');
    }

    private function money(string|float|null $amount): string
    {
        return bcadd((string) $amount, '0', 2);
    }
}
