<?php

namespace Tests\Feature\Pricing;

use App\Http\Resources\Client\ClientShipmentOrderPriceSnapshotResource;
use App\Models\ShipmentOrderPriceBreakdown;
use Illuminate\Http\Request;
use Tests\TestCase;

class ClientShipmentOrderPriceSnapshotResourceTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleBreakdownJson(array $overrides = []): array
    {
        return array_merge([
            'currency' => 'USD',
            'pricingPolicyId' => 1,
            'pricingPolicyName' => 'Standard 2026',
            'input' => [
                'distance' => 500.0,
                'cargoType' => 'general',
                'trailerType' => 'dry_van',
                'cargoWeight' => 15.0,
                'shipmentPriority' => 'standard',
                'customerType' => 'corporate',
                'borderCrossing' => false,
                'hazmat' => false,
                'insuranceRequired' => false,
                'selectedServices' => [],
                'loadingWaitingTime' => 0,
                'unloadingWaitingTime' => 0,
                'trafficDelay' => 0,
                'customerDelay' => 0,
            ],
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
            'serviceItems' => [['name' => 'GPS Seal', 'price' => 15.0]],
            'appliedRules' => ['base_rate:general/dry_van(exact)'],
            'warnings' => ['Simulator fallback used for routing'],
            'baseRate' => 1.45,
            'distanceTierRate' => 0.80,
            'baseRateResolutionType' => 'exact',
            'taxRate' => 8.0,
            'minimumFare' => null,
            'discountType' => 'percentage',
            'discountRate' => 5.0,
        ], $overrides);
    }

    private function makeBreakdownModel(array $jsonOverrides = []): ShipmentOrderPriceBreakdown
    {
        $model = new ShipmentOrderPriceBreakdown([
            'pricing_policy_id' => 1,
            'breakdown_json' => $this->sampleBreakdownJson($jsonOverrides),
            'final_total' => 1449.23,
            'currency' => 'USD',
            'calculated_at' => now()->setDate(2026, 8, 19)->setTime(18, 0, 0),
        ]);

        return $model;
    }

    public function test_resource_returns_correct_structure(): void
    {
        $result = (new ClientShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertArrayHasKey('calculated_at', $result);
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

    public function test_total_matches_persisted_json(): void
    {
        $result = (new ClientShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertSame(1449.23, $result['total']);
    }

    public function test_distance_km_from_persisted_input(): void
    {
        $model = $this->makeBreakdownModel(['input' => array_merge(
            $this->sampleBreakdownJson()['input'],
            ['distance' => 123.45]
        )]);

        $result = (new ClientShipmentOrderPriceSnapshotResource($model))->toArray(Request::create('/'));

        $this->assertSame(123.45, $result['distance_km']);
    }

    public function test_charges_mapped_from_persisted_json(): void
    {
        $result = (new ClientShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertSame(1125.0, $result['charges']['freight']);
        $this->assertSame(265.0, $result['charges']['services']);
        $this->assertSame(22.5, $result['charges']['insurance']);
    }

    public function test_service_items_represented(): void
    {
        $result = (new ClientShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertCount(1, $result['service_items']);
        $this->assertSame('GPS Seal', $result['service_items'][0]['name']);
    }

    public function test_warnings_represented(): void
    {
        $result = (new ClientShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

        $this->assertSame(['Simulator fallback used for routing'], $result['warnings']);
    }

    public function test_no_internal_policy_details_exposed(): void
    {
        $result = (new ClientShipmentOrderPriceSnapshotResource($this->makeBreakdownModel()))
            ->toArray(Request::create('/'));

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
        $this->assertArrayNotHasKey('breakdown_json', $result);
    }

    public function test_malformed_json_returns_degraded_fallback(): void
    {
        $model = new ShipmentOrderPriceBreakdown([
            'breakdown_json' => ['invalid' => true],
            'final_total' => 999.99,
            'currency' => 'EUR',
            'calculated_at' => now(),
        ]);

        $result = (new ClientShipmentOrderPriceSnapshotResource($model))->toArray(Request::create('/'));

        $this->assertSame('EUR', $result['currency']);
        $this->assertSame(999.99, $result['total']);
        $this->assertSame(['Price breakdown detail unavailable'], $result['warnings']);
        $this->assertArrayNotHasKey('charges', $result);
    }
}
