<?php

namespace Tests\Feature\Pricing;

use App\Http\Resources\Client\ClientPricingEstimateResource;
use App\Services\Pricing\PricingBreakdown;
use App\Services\Pricing\PricingInput;
use App\Enums\CargoType;
use App\Enums\TrailerType;
use Illuminate\Http\Request;
use Tests\TestCase;

class ClientPricingEstimateResourceTest extends TestCase
{
    private function makeBreakdown(array $overrides = []): PricingBreakdown
    {
        $defaults = [
            'currency' => 'USD',
            'pricingPolicyId' => 1,
            'pricingPolicyName' => 'Standard 2026',
            'input' => new PricingInput(500, CargoType::GENERAL, TrailerType::DRY_VAN, 15, 'standard', 'corporate'),
            'freightCharge' => 1125.0,
            'weightSurcharge' => 0.0,
            'waitingCharge' => 0.0,
            'serviceCharges' => 265.0,
            'borderFee' => 0.0,
            'insuranceFee' => 22.5,
            'hazmatSurcharge' => 0.0,
            'heavyReeferSurcharge' => 0.0,
            'prioritySurcharge' => 0.0,
            'subtotal' => 1412.5,
            'discount' => 70.625,
            'afterDiscount' => 1341.875,
            'minimumFareAdjustment' => 0.0,
            'afterMinimumFare' => 1341.875,
            'tax' => 107.35,
            'finalTotal' => 1449.23,
            'serviceItems' => [['name' => 'Loading Crew', 'price' => 250.0], ['name' => 'GPS Seal', 'price' => 15.0]],
            'appliedRules' => ['base_rate:general/dry_van(exact)', 'insurance_fee', 'discount:corporate'],
            'warnings' => ['Simulator fallback used for routing'],
            'baseRate' => 1.45,
            'distanceTierRate' => 0.80,
            'baseRateResolutionType' => 'exact',
            'taxRate' => 8.0,
            'minimumFare' => null,
            'discountType' => 'percentage',
            'discountRate' => 5.0,
        ];

        return new PricingBreakdown(...array_merge($defaults, $overrides));
    }

    public function test_resource_returns_correct_structure(): void
    {
        $breakdown = $this->makeBreakdown();
        $resource = new ClientPricingEstimateResource($breakdown, 500.0);
        $result = $resource->toArray(Request::create('/'));

        $this->assertArrayHasKey('currency', $result);
        $this->assertArrayHasKey('distance_km', $result);
        $this->assertArrayHasKey('charges', $result);
        $this->assertArrayHasKey('subtotal', $result);
        $this->assertArrayHasKey('discount', $result);
        $this->assertArrayHasKey('tax', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('service_items', $result);
        $this->assertArrayHasKey('warnings', $result);
    }

    public function test_currency_returned_correctly(): void
    {
        $breakdown = $this->makeBreakdown(['currency' => 'EUR']);
        $result = (new ClientPricingEstimateResource($breakdown, 300.0))->toArray(Request::create('/'));

        $this->assertEquals('EUR', $result['currency']);
    }

    public function test_total_matches_breakdown(): void
    {
        $breakdown = $this->makeBreakdown(['finalTotal' => 1449.23]);
        $result = (new ClientPricingEstimateResource($breakdown, 500.0))->toArray(Request::create('/'));

        $this->assertEquals(1449.23, $result['total']);
    }

    public function test_subtotal_matches_breakdown(): void
    {
        $breakdown = $this->makeBreakdown(['subtotal' => 1412.5]);
        $result = (new ClientPricingEstimateResource($breakdown, 500.0))->toArray(Request::create('/'));

        $this->assertEquals(1412.5, $result['subtotal']);
    }

    public function test_discount_represented_correctly(): void
    {
        $breakdown = $this->makeBreakdown([
            'discount' => 70.625,
            'discountType' => 'percentage',
            'discountRate' => 5.0,
        ]);
        $result = (new ClientPricingEstimateResource($breakdown, 500.0))->toArray(Request::create('/'));

        $this->assertEquals(70.625, $result['discount']['amount']);
        $this->assertEquals('percentage', $result['discount']['type']);
        $this->assertEquals(5.0, $result['discount']['rate']);
    }

    public function test_tax_represented_correctly(): void
    {
        $breakdown = $this->makeBreakdown(['tax' => 107.35, 'taxRate' => 8.0]);
        $result = (new ClientPricingEstimateResource($breakdown, 500.0))->toArray(Request::create('/'));

        $this->assertEquals(107.35, $result['tax']['amount']);
        $this->assertEquals(8.0, $result['tax']['rate']);
    }

    public function test_service_items_represented(): void
    {
        $breakdown = $this->makeBreakdown([
            'serviceItems' => [['name' => 'GPS Seal', 'price' => 15.0]],
        ]);
        $result = (new ClientPricingEstimateResource($breakdown, 500.0))->toArray(Request::create('/'));

        $this->assertCount(1, $result['service_items']);
        $this->assertEquals('GPS Seal', $result['service_items'][0]['name']);
        $this->assertEquals(15.0, $result['service_items'][0]['price']);
    }

    public function test_warnings_represented(): void
    {
        $breakdown = $this->makeBreakdown(['warnings' => ['Low accuracy routing']]);
        $result = (new ClientPricingEstimateResource($breakdown, 500.0))->toArray(Request::create('/'));

        $this->assertEquals(['Low accuracy routing'], $result['warnings']);
    }

    public function test_no_internal_policy_details_exposed(): void
    {
        $breakdown = $this->makeBreakdown();
        $result = (new ClientPricingEstimateResource($breakdown, 500.0))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('pricingPolicyId', $result);
        $this->assertArrayNotHasKey('pricingPolicyName', $result);
        $this->assertArrayNotHasKey('pricing_policy_id', $result);
        $this->assertArrayNotHasKey('baseRate', $result);
        $this->assertArrayNotHasKey('base_rate', $result);
        $this->assertArrayNotHasKey('distanceTierRate', $result);
        $this->assertArrayNotHasKey('baseRateResolutionType', $result);
        $this->assertArrayNotHasKey('appliedRules', $result);
        $this->assertArrayNotHasKey('applied_rules', $result);
        $this->assertArrayNotHasKey('input', $result);
    }

    public function test_distance_km_from_constructor(): void
    {
        $breakdown = $this->makeBreakdown();
        $result = (new ClientPricingEstimateResource($breakdown, 123.45))->toArray(Request::create('/'));

        $this->assertEquals(123.45, $result['distance_km']);
    }
}
