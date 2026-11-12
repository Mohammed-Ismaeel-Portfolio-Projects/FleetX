<?php

namespace Tests\Feature\Pricing\Support;

use App\Enums\CargoType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\PricingBaseRate;
use App\Models\PricingDiscount;
use App\Models\PricingDistanceTier;
use App\Models\PricingFee;
use App\Models\PricingPolicy;
use App\Models\PricingPriorityMultiplier;
use App\Models\PricingWaitingConfig;
use App\Models\PricingWeightConfig;
use App\Services\Routing\TruckRouteDistanceCalculator;
use Mockery;

trait SetsUpPricingForShipmentOrders
{
    protected function createFullPricingPolicy(): PricingPolicy
    {
        $policy = PricingPolicy::create([
            'name' => 'Standard Freight 2026',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 8.0000,
            'tax_inclusive' => false,
            'minimum_fare' => null,
        ]);

        PricingBaseRate::create(['pricing_policy_id' => $policy->id, 'cargo_type' => CargoType::GENERAL, 'trailer_type' => TrailerType::DRY_VAN, 'rate_per_km' => 1.45]);
        PricingBaseRate::create(['pricing_policy_id' => $policy->id, 'cargo_type' => CargoType::REFRIGERATED, 'trailer_type' => TrailerType::REEFER, 'rate_per_km' => 1.95]);
        PricingBaseRate::create(['pricing_policy_id' => $policy->id, 'cargo_type' => CargoType::HEAVY, 'trailer_type' => TrailerType::LOWBOY, 'rate_per_km' => 2.85]);
        PricingBaseRate::create(['pricing_policy_id' => $policy->id, 'cargo_type' => CargoType::HAZARDOUS, 'trailer_type' => TrailerType::TANKER, 'rate_per_km' => 2.50]);

        PricingDistanceTier::create(['pricing_policy_id' => $policy->id, 'min_distance_km' => 0, 'max_distance_km' => 100, 'rate_per_km' => 1.20]);
        PricingDistanceTier::create(['pricing_policy_id' => $policy->id, 'min_distance_km' => 101, 'max_distance_km' => 300, 'rate_per_km' => 0.95]);
        PricingDistanceTier::create(['pricing_policy_id' => $policy->id, 'min_distance_km' => 301, 'max_distance_km' => 700, 'rate_per_km' => 0.80]);
        PricingDistanceTier::create(['pricing_policy_id' => $policy->id, 'min_distance_km' => 701, 'max_distance_km' => null, 'rate_per_km' => 0.65]);

        PricingWeightConfig::create(['pricing_policy_id' => $policy->id, 'threshold_tons' => 20, 'charge_per_ton' => 50]);
        PricingWaitingConfig::create(['pricing_policy_id' => $policy->id, 'loading_free_minutes' => 30, 'unloading_free_minutes' => 30, 'rate_per_15min' => 15, 'max_charge' => 200, 'enabled' => true]);

        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'border', 'name' => 'Border Crossing', 'fee_type' => 'flat', 'amount' => 150, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'insurance', 'name' => 'Insurance', 'fee_type' => 'percentage', 'amount' => 2, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'hazmat', 'name' => 'Hazmat', 'fee_type' => 'percentage', 'amount' => 25, 'enabled' => true]);
        PricingFee::create(['pricing_policy_id' => $policy->id, 'code' => 'heavy_reefer', 'name' => 'Heavy Reefer', 'fee_type' => 'percentage', 'amount' => 6, 'enabled' => true]);

        foreach (['standard' => 1.0, 'express' => 1.1, 'urgent' => 1.2, 'emergency' => 1.3] as $level => $mult) {
            PricingPriorityMultiplier::create(['pricing_policy_id' => $policy->id, 'priority_level' => $level, 'multiplier' => $mult]);
        }

        PricingDiscount::create(['pricing_policy_id' => $policy->id, 'customer_type' => 'corporate', 'discount_type' => 'percentage', 'amount' => 5, 'enabled' => true]);
        PricingDiscount::create(['pricing_policy_id' => $policy->id, 'customer_type' => 'vip', 'discount_type' => 'percentage', 'amount' => 10, 'enabled' => true]);

        return $policy;
    }

    /**
     * Valid draft policy payload for governance API tests (PRICING-04).
     *
     * @return array<string, mixed>
     */
    protected function validDraftPolicyPayload(string $name = 'Draft Policy 2026'): array
    {
        return [
            'name' => $name,
            'description' => 'Test draft pricing policy.',
            'currency' => 'USD',
            'effective_from' => null,
            'effective_until' => null,
            'priority' => 5,
            'tax_rate' => 8,
            'tax_inclusive' => false,
            'minimum_fare' => null,
            'base_rates' => [
                ['cargo_type' => 'general', 'trailer_type' => 'dry_van', 'rate_per_km' => 1.45],
                ['cargo_type' => 'refrigerated', 'trailer_type' => 'reefer', 'rate_per_km' => 1.95],
                ['cargo_type' => 'heavy', 'trailer_type' => 'lowboy', 'rate_per_km' => 2.85],
                ['cargo_type' => 'hazardous', 'trailer_type' => 'tanker', 'rate_per_km' => 2.50],
                ['cargo_type' => 'fragile', 'trailer_type' => 'dry_van', 'rate_per_km' => 1.55],
            ],
            'distance_tiers' => [
                ['min_distance_km' => 0, 'max_distance_km' => 100, 'rate_per_km' => 1.20],
                ['min_distance_km' => 101, 'max_distance_km' => 300, 'rate_per_km' => 0.95],
                ['min_distance_km' => 301, 'max_distance_km' => 700, 'rate_per_km' => 0.80],
                ['min_distance_km' => 701, 'max_distance_km' => null, 'rate_per_km' => 0.65],
            ],
            'weight_config' => [
                'threshold_tons' => 20,
                'charge_per_ton' => 50,
            ],
            'waiting_config' => [
                'loading_free_minutes' => 30,
                'unloading_free_minutes' => 30,
                'rate_per_15min' => 15,
                'max_charge' => 200,
                'enabled' => true,
            ],
            'fees' => [
                ['code' => 'border', 'name' => 'Border Crossing', 'fee_type' => 'flat', 'amount' => 150, 'enabled' => true],
            ],
            'priority_multipliers' => [
                ['priority_level' => 'standard', 'multiplier' => 1.0],
                ['priority_level' => 'express', 'multiplier' => 1.1],
            ],
            'discounts' => [
                ['customer_type' => 'corporate', 'discount_type' => 'percentage', 'amount' => 5, 'enabled' => true],
            ],
            'service_catalog' => [],
            'variance_rules' => [],
        ];
    }

    protected function mockTruckRouteDistance(float $km): void
    {
        $mock = Mockery::mock(TruckRouteDistanceCalculator::class);
        $mock->shouldReceive('calculate')->andReturn($km);
        $this->app->instance(TruckRouteDistanceCalculator::class, $mock);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pricingReadyOrderAttributes(): array
    {
        return [
            'cargo_type' => 'general',
            'trailer_type' => 'dry_van',
            'cargo_weight' => 15.0,
            'pickup_latitude' => 52.2297,
            'pickup_longitude' => 21.0122,
            'delivery_latitude' => 50.0647,
            'delivery_longitude' => 19.9450,
        ];
    }
}
