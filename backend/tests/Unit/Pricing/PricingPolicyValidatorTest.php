<?php

namespace Tests\Unit\Pricing;

use App\Enums\CargoType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\PricingBaseRate;
use App\Models\PricingDistanceTier;
use App\Models\PricingPolicy;
use App\Services\Pricing\PricingPolicyValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingPolicyValidatorTest extends TestCase
{
    use RefreshDatabase;

    private PricingPolicyValidator $validator;
    private PricingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PricingPolicyValidator();
        $this->policy = PricingPolicy::create([
            'name' => 'Validation Test Policy',
            'status' => PricingPolicyStatus::DRAFT,
            'currency' => 'USD',
            'tax_rate' => 0.0800,
        ]);
    }

    // ─── Base Rate Validation ────────────────────────────────────────

    public function test_fails_when_no_base_rates(): void
    {
        $this->seedValidTiers();

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertNotEmpty($errors);
        $this->assertContains('No base rates configured.', $errors);
    }

    public function test_fails_when_cargo_type_missing_rate(): void
    {
        // Only add general, missing others
        PricingBaseRate::create([
            'pricing_policy_id' => $this->policy->id,
            'cargo_type' => CargoType::GENERAL,
            'trailer_type' => TrailerType::DRY_VAN,
            'rate_per_km' => 1.45,
        ]);
        $this->seedValidTiers();

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertContains("No base rate configured for cargo type 'refrigerated'.", $errors);
        $this->assertContains("No base rate configured for cargo type 'heavy'.", $errors);
    }

    public function test_fails_when_rate_is_zero(): void
    {
        $this->seedAllCargoRates(0.0000);
        $this->seedValidTiers();

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertNotEmpty($errors);
        $this->assertTrue(
            collect($errors)->contains(fn ($e) => str_contains($e, 'must be > 0'))
        );
    }

    public function test_fails_when_rate_is_negative(): void
    {
        $this->seedAllCargoRates(-1.50);
        $this->seedValidTiers();

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertNotEmpty($errors);
    }

    // ─── Distance Tier Validation ────────────────────────────────────

    public function test_fails_when_no_distance_tiers(): void
    {
        $this->seedAllCargoRates(1.45);

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertContains('No distance tiers configured.', $errors);
    }

    public function test_fails_when_first_tier_does_not_start_at_zero(): void
    {
        $this->seedAllCargoRates(1.45);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 50,
            'max_distance_km' => null,
            'rate_per_km' => 1.00,
        ]);

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertTrue(
            collect($errors)->contains(fn ($e) => str_contains($e, 'must start at 0'))
        );
    }

    public function test_fails_when_last_tier_is_not_unbounded(): void
    {
        $this->seedAllCargoRates(1.45);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 0,
            'max_distance_km' => 500,
            'rate_per_km' => 1.00,
        ]);

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertTrue(
            collect($errors)->contains(fn ($e) => str_contains($e, 'unlimited max'))
        );
    }

    public function test_fails_when_tiers_overlap(): void
    {
        $this->seedAllCargoRates(1.45);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 0,
            'max_distance_km' => 200,
            'rate_per_km' => 1.20,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 150, // overlaps with previous
            'max_distance_km' => null,
            'rate_per_km' => 0.80,
        ]);

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertTrue(
            collect($errors)->contains(fn ($e) => str_contains($e, 'overlap'))
        );
    }

    public function test_fails_when_tiers_have_gap(): void
    {
        $this->seedAllCargoRates(1.45);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 0,
            'max_distance_km' => 100,
            'rate_per_km' => 1.20,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 200, // gap: 101-199 uncovered
            'max_distance_km' => null,
            'rate_per_km' => 0.80,
        ]);

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertTrue(
            collect($errors)->contains(fn ($e) => str_contains($e, 'Gap'))
        );
    }

    public function test_fails_when_tier_rate_is_zero(): void
    {
        $this->seedAllCargoRates(1.45);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 0,
            'max_distance_km' => null,
            'rate_per_km' => 0,
        ]);

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertTrue(
            collect($errors)->contains(fn ($e) => str_contains($e, 'rate must be > 0'))
        );
    }

    public function test_fails_when_tier_has_max_less_than_min(): void
    {
        $this->seedAllCargoRates(1.45);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 100,
            'max_distance_km' => 50,
            'rate_per_km' => 1.00,
        ]);

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertTrue(
            collect($errors)->contains(fn ($e) => str_contains($e, 'max <= min'))
        );
    }

    // ─── Valid Configuration ─────────────────────────────────────────

    public function test_passes_with_valid_complete_configuration(): void
    {
        $this->seedAllCargoRates(1.45);
        $this->seedValidTiers();

        $errors = $this->validator->validateRateConfiguration($this->policy);

        $this->assertEmpty($errors, implode("\n", $errors));
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function seedAllCargoRates(float $rate): void
    {
        foreach (CargoType::cases() as $cargoType) {
            PricingBaseRate::create([
                'pricing_policy_id' => $this->policy->id,
                'cargo_type' => $cargoType,
                'trailer_type' => null,
                'rate_per_km' => $rate,
            ]);
        }
    }

    private function seedValidTiers(): void
    {
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 0,
            'max_distance_km' => 100,
            'rate_per_km' => 1.2000,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 101,
            'max_distance_km' => 300,
            'rate_per_km' => 0.9500,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 301,
            'max_distance_km' => 700,
            'rate_per_km' => 0.8000,
        ]);
        PricingDistanceTier::create([
            'pricing_policy_id' => $this->policy->id,
            'min_distance_km' => 701,
            'max_distance_km' => null,
            'rate_per_km' => 0.6500,
        ]);
    }
}
