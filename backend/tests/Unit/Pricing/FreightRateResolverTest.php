<?php

namespace Tests\Unit\Pricing;

use App\Enums\CargoType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\PricingBaseRate;
use App\Models\PricingDistanceTier;
use App\Models\PricingPolicy;
use App\Services\Pricing\Exceptions\MissingBaseRateException;
use App\Services\Pricing\Exceptions\MissingDistanceTierException;
use App\Services\Pricing\FreightRateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreightRateResolverTest extends TestCase
{
    use RefreshDatabase;

    private FreightRateResolver $resolver;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new FreightRateResolver();
        $this->policy = PricingPolicy::create([
            'name' => 'Test Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);

        $this->seedStandardRates($this->policy);
        $this->seedStandardTiers($this->policy);
    }

    // ─── Base Rate Resolution ────────────────────────────────────────

    public function test_resolves_exact_cargo_trailer_match(): void
    {
        $result = $this->resolver->resolve(
            $this->policy,
            CargoType::GENERAL,
            TrailerType::DRY_VAN,
            500,
        );

        $this->assertEquals(1.4500, $result->baseRate);
        $this->assertEquals('exact', $result->baseRateResolutionType);
    }

    public function test_resolves_cargo_fallback_when_no_exact_match(): void
    {
        // Add a cargo-only fallback for fragile (no exact trailer match exists)
        PricingBaseRate::create([
            'pricing_policy_id' => $this->policy->id,
            'cargo_type' => CargoType::FRAGILE,
            'trailer_type' => null,
            'rate_per_km' => 1.7500,
        ]);

        $result = $this->resolver->resolve(
            $this->policy,
            CargoType::FRAGILE,
            TrailerType::FLATBED,
            200,
        );

        $this->assertEquals(1.7500, $result->baseRate);
        $this->assertEquals('cargo_fallback', $result->baseRateResolutionType);
    }

    public function test_exact_match_takes_priority_over_cargo_fallback(): void
    {
        // Add cargo-only fallback AND exact match for general
        PricingBaseRate::create([
            'pricing_policy_id' => $this->policy->id,
            'cargo_type' => CargoType::GENERAL,
            'trailer_type' => null,
            'rate_per_km' => 1.0000,
        ]);

        $result = $this->resolver->resolve(
            $this->policy,
            CargoType::GENERAL,
            TrailerType::DRY_VAN,
            100,
        );

        // Should use exact match (1.45), not fallback (1.00)
        $this->assertEquals(1.4500, $result->baseRate);
        $this->assertEquals('exact', $result->baseRateResolutionType);
    }

    public function test_throws_when_no_base_rate_exists(): void
    {
        $this->expectException(MissingBaseRateException::class);

        $this->resolver->resolve(
            $this->policy,
            CargoType::FRAGILE, // no rate configured
            TrailerType::TANKER,
            100,
        );
    }

    // ─── Distance Tier Resolution ────────────────────────────────────

    public function test_resolves_tier_for_0_km(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 0,
        );
        $this->assertEquals(1.2000, $result->distanceTierRate);
    }

    public function test_resolves_tier_for_100_km(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 100,
        );
        $this->assertEquals(1.2000, $result->distanceTierRate);
    }

    public function test_resolves_tier_for_101_km(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 101,
        );
        $this->assertEquals(0.9500, $result->distanceTierRate);
    }

    public function test_resolves_tier_for_300_km(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 300,
        );
        $this->assertEquals(0.9500, $result->distanceTierRate);
    }

    public function test_resolves_tier_for_301_km(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 301,
        );
        $this->assertEquals(0.8000, $result->distanceTierRate);
    }

    public function test_resolves_tier_for_700_km(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 700,
        );
        $this->assertEquals(0.8000, $result->distanceTierRate);
    }

    public function test_resolves_tier_for_701_km(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 701,
        );
        $this->assertEquals(0.6500, $result->distanceTierRate);
    }

    public function test_resolves_tier_for_very_large_distance(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 50000,
        );
        $this->assertEquals(0.6500, $result->distanceTierRate);
    }

    public function test_throws_when_no_tier_covers_distance(): void
    {
        // Create policy with incomplete tiers
        $emptyPolicy = PricingPolicy::create([
            'name' => 'Incomplete Policy',
            'status' => PricingPolicyStatus::DRAFT,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
        PricingBaseRate::create([
            'pricing_policy_id' => $emptyPolicy->id,
            'cargo_type' => CargoType::GENERAL,
            'trailer_type' => TrailerType::DRY_VAN,
            'rate_per_km' => 1.0000,
        ]);
        // Only tier 0-100
        PricingDistanceTier::create([
            'pricing_policy_id' => $emptyPolicy->id,
            'min_distance_km' => 0,
            'max_distance_km' => 100,
            'rate_per_km' => 1.2000,
        ]);

        $this->expectException(MissingDistanceTierException::class);

        $this->resolver->resolve(
            $emptyPolicy, CargoType::GENERAL, TrailerType::DRY_VAN, 200,
        );
    }

    // ─── Policy Isolation ────────────────────────────────────────────

    public function test_policy_rates_do_not_leak_between_policies(): void
    {
        $otherPolicy = PricingPolicy::create([
            'name' => 'Other Policy',
            'status' => PricingPolicyStatus::ACTIVE,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
        PricingBaseRate::create([
            'pricing_policy_id' => $otherPolicy->id,
            'cargo_type' => CargoType::GENERAL,
            'trailer_type' => TrailerType::DRY_VAN,
            'rate_per_km' => 9.9900, // different rate
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $otherPolicy->id,
            'min_distance_km' => 0,
            'max_distance_km' => null,
            'rate_per_km' => 5.0000,
        ]);

        // Original policy should still resolve its own rates
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 50,
        );
        $this->assertEquals(1.4500, $result->baseRate);
        $this->assertEquals(1.2000, $result->distanceTierRate);

        // Other policy should resolve its own rates
        $otherResult = $this->resolver->resolve(
            $otherPolicy, CargoType::GENERAL, TrailerType::DRY_VAN, 50,
        );
        $this->assertEquals(9.9900, $otherResult->baseRate);
        $this->assertEquals(5.0000, $otherResult->distanceTierRate);
    }

    // ─── Resolution Result ───────────────────────────────────────────

    public function test_resolution_contains_policy_id(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 500,
        );
        $this->assertEquals($this->policy->id, $result->policyId);
    }

    public function test_resolution_contains_matched_record_ids(): void
    {
        $result = $this->resolver->resolve(
            $this->policy, CargoType::GENERAL, TrailerType::DRY_VAN, 500,
        );
        $this->assertIsInt($result->matchedBaseRateId);
        $this->assertIsInt($result->matchedDistanceTierId);
        $this->assertGreaterThan(0, $result->matchedBaseRateId);
        $this->assertGreaterThan(0, $result->matchedDistanceTierId);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function seedStandardRates(PricingPolicy $policy): void
    {
        PricingBaseRate::create([
            'pricing_policy_id' => $policy->id,
            'cargo_type' => CargoType::GENERAL,
            'trailer_type' => TrailerType::DRY_VAN,
            'rate_per_km' => 1.4500,
        ]);
        PricingBaseRate::create([
            'pricing_policy_id' => $policy->id,
            'cargo_type' => CargoType::REFRIGERATED,
            'trailer_type' => TrailerType::REEFER,
            'rate_per_km' => 1.9500,
        ]);
        PricingBaseRate::create([
            'pricing_policy_id' => $policy->id,
            'cargo_type' => CargoType::HEAVY,
            'trailer_type' => TrailerType::LOWBOY,
            'rate_per_km' => 2.8500,
        ]);
        PricingBaseRate::create([
            'pricing_policy_id' => $policy->id,
            'cargo_type' => CargoType::HAZARDOUS,
            'trailer_type' => TrailerType::TANKER,
            'rate_per_km' => 2.5000,
        ]);
    }

    private function seedStandardTiers(PricingPolicy $policy): void
    {
        PricingDistanceTier::create([
            'pricing_policy_id' => $policy->id,
            'min_distance_km' => 0,
            'max_distance_km' => 100,
            'rate_per_km' => 1.2000,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $policy->id,
            'min_distance_km' => 101,
            'max_distance_km' => 300,
            'rate_per_km' => 0.9500,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $policy->id,
            'min_distance_km' => 301,
            'max_distance_km' => 700,
            'rate_per_km' => 0.8000,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $policy->id,
            'min_distance_km' => 701,
            'max_distance_km' => null,
            'rate_per_km' => 0.6500,
        ]);
    }
}
