<?php

namespace Tests\Unit\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\Company;
use App\Models\PricingBaseRate;
use App\Models\PricingDistanceTier;
use App\Models\PricingPolicy;
use App\Models\PricingPriorityMultiplier;
use App\Models\PricingServiceCatalog;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Services\Pricing\Exceptions\ServiceCatalogException;
use App\Services\Pricing\PricingBreakdown;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingInput;
use App\Services\Pricing\ServiceCatalogResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase51IntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── 5.1A: Customer Type ─────────────────────────────────────────

    public function test_customer_type_enum_has_exactly_four_values(): void
    {
        $cases = CustomerType::cases();

        $this->assertCount(4, $cases);
        $this->assertNotNull(CustomerType::tryFrom('standard'));
        $this->assertNotNull(CustomerType::tryFrom('corporate'));
        $this->assertNotNull(CustomerType::tryFrom('vip'));
        $this->assertNotNull(CustomerType::tryFrom('long_term'));
    }

    public function test_company_customer_type_casts_correctly(): void
    {
        $company = Company::factory()->create(['customer_type' => 'corporate']);

        $this->assertInstanceOf(CustomerType::class, $company->customer_type);
        $this->assertEquals(CustomerType::CORPORATE, $company->customer_type);
    }

    public function test_company_customer_type_nullable(): void
    {
        $company = Company::factory()->create(['customer_type' => null]);

        $this->assertNull($company->customer_type);
    }

    public function test_existing_company_creation_not_broken(): void
    {
        $company = Company::factory()->create();

        $this->assertNotNull($company->id);
    }

    // ─── 5.1B: Service Catalog ───────────────────────────────────────

    public function test_service_belongs_to_pricing_policy(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $policy->id,
            'name' => 'GPS Seal',
            'price' => 15.00,
            'enabled' => true,
        ]);

        $this->assertEquals($policy->id, $service->pricingPolicy->id);
    }

    public function test_policy_exposes_service_catalog_relationship(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        PricingServiceCatalog::create([
            'pricing_policy_id' => $policy->id,
            'name' => 'Loading Crew',
            'price' => 250.00,
            'enabled' => true,
        ]);

        $this->assertCount(1, $policy->serviceCatalogItems);
    }

    public function test_enabled_service_resolves_successfully(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $policy->id,
            'name' => 'GPS Seal',
            'price' => 15.00,
            'enabled' => true,
        ]);

        $resolver = new ServiceCatalogResolver();
        $result = $resolver->resolve($policy, [$service->id]);

        $this->assertCount(1, $result);
        $this->assertEquals('GPS Seal', $result[0]['name']);
        $this->assertEquals(15.00, $result[0]['price']);
    }

    public function test_disabled_service_is_rejected(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $policy->id,
            'name' => 'Discontinued',
            'price' => 100.00,
            'enabled' => false,
        ]);

        $resolver = new ServiceCatalogResolver();

        $this->expectException(ServiceCatalogException::class);
        $resolver->resolve($policy, [$service->id]);
    }

    public function test_service_from_another_policy_is_rejected(): void
    {
        $policy1 = PricingPolicy::create([
            'name' => 'P1', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);
        $policy2 = PricingPolicy::create([
            'name' => 'P2', 'status' => PricingPolicyStatus::DRAFT,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $policy2->id,
            'name' => 'Other Policy Service',
            'price' => 50.00,
            'enabled' => true,
        ]);

        $resolver = new ServiceCatalogResolver();

        $this->expectException(ServiceCatalogException::class);
        $resolver->resolve($policy1, [$service->id]);
    }

    public function test_unknown_service_id_is_rejected(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $resolver = new ServiceCatalogResolver();

        $this->expectException(ServiceCatalogException::class);
        $resolver->resolve($policy, [99999]);
    }

    public function test_catalog_price_is_authoritative(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $service = PricingServiceCatalog::create([
            'pricing_policy_id' => $policy->id,
            'name' => 'Loading Crew',
            'price' => 250.00,
            'enabled' => true,
        ]);

        $resolver = new ServiceCatalogResolver();
        $result = $resolver->resolve($policy, [$service->id]);

        $this->assertEquals(250.00, $result[0]['price']);
        $this->assertEquals('Loading Crew', $result[0]['name']);
    }

    // ─── 5.1C: Breakdown Persistence ─────────────────────────────────

    public function test_shipment_order_has_one_price_breakdown(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $order = ShipmentOrder::factory()->create();

        $breakdown = ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => ['finalTotal' => 1500.00, 'currency' => 'USD'],
            'final_total' => 1500.00,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $this->assertNotNull($order->priceBreakdown);
        $this->assertEquals(1500.00, (float) $order->priceBreakdown->final_total);
    }

    public function test_breakdown_belongs_to_pricing_policy(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test Policy', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $order = ShipmentOrder::factory()->create();

        $breakdown = ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => ['test' => true],
            'final_total' => 100.00,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $this->assertEquals($policy->id, $breakdown->pricingPolicy->id);
    }

    public function test_breakdown_json_persists_and_deserializes(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $order = ShipmentOrder::factory()->create();

        $breakdownData = [
            'currency' => 'USD',
            'pricingPolicyId' => $policy->id,
            'freightCharge' => 1125.0,
            'weightSurcharge' => 0.0,
            'waitingCharge' => 0.0,
            'serviceCharges' => 265.0,
            'subtotal' => 1412.5,
            'discount' => 70.625,
            'afterDiscount' => 1341.875,
            'tax' => 107.35,
            'finalTotal' => 1449.23,
            'appliedRules' => ['base_rate:general/dry_van(exact)', 'insurance_fee'],
        ];

        ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => $breakdownData,
            'final_total' => 1449.23,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $persisted = ShipmentOrderPriceBreakdown::where('shipment_order_id', $order->id)->first();

        $this->assertEquals($breakdownData, $persisted->breakdown_json);
        $this->assertEquals('1449.23', $persisted->final_total);
        $this->assertEquals('USD', $persisted->currency);
    }

    public function test_calculated_at_persists(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Test', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $order = ShipmentOrder::factory()->create();
        $calcTime = now()->subHour();

        ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => ['test' => true],
            'final_total' => 100.00,
            'currency' => 'USD',
            'calculated_at' => $calcTime,
        ]);

        $persisted = ShipmentOrderPriceBreakdown::where('shipment_order_id', $order->id)->first();
        $this->assertEquals($calcTime->format('Y-m-d H:i:s'), $persisted->calculated_at->format('Y-m-d H:i:s'));
    }

    public function test_snapshot_unchanged_after_policy_modification(): void
    {
        $policy = PricingPolicy::create([
            'name' => 'Original', 'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD', 'tax_rate' => 8, 'tax_inclusive' => false,
        ]);

        $order = ShipmentOrder::factory()->create();

        ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => ['taxRate' => 8.0, 'finalTotal' => 1000.00],
            'final_total' => 1000.00,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        // Modify policy
        $policy->update(['tax_rate' => 15, 'name' => 'Modified']);

        // Snapshot unchanged
        $persisted = ShipmentOrderPriceBreakdown::where('shipment_order_id', $order->id)->first();
        $this->assertEquals(8.0, $persisted->breakdown_json['taxRate']);
        $this->assertEquals('1000.00', $persisted->final_total);
    }
}
