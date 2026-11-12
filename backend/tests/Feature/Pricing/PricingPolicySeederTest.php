<?php

namespace Tests\Feature\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\PricingPolicyStatus;
use App\Enums\TrailerType;
use App\Models\PricingPolicy;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingInputFactory;
use App\Services\Pricing\PricingPolicyResolver;
use App\Services\Pricing\PricingPolicyValidator;
use Database\Seeders\PricingPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingPolicySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_a_valid_active_pricing_policy(): void
    {
        $this->seed(PricingPolicySeeder::class);

        $policy = PricingPolicy::query()->where('name', PricingPolicySeeder::POLICY_NAME)->first();

        $this->assertNotNull($policy);
        $this->assertSame(PricingPolicyStatus::ACTIVE, $policy->status);
        $this->assertSame('USD', $policy->currency);
        $this->assertGreaterThan(0, $policy->baseRates()->count());
        $this->assertGreaterThan(0, $policy->distanceTiers()->count());
        $this->assertNotNull($policy->weightConfig);
        $this->assertNotNull($policy->waitingConfig);
        $this->assertGreaterThan(0, $policy->fees()->count());
        $this->assertGreaterThan(0, $policy->priorityMultipliers()->count());
        $this->assertGreaterThan(0, $policy->discounts()->count());
        $this->assertGreaterThan(0, $policy->serviceCatalogItems()->count());
        $this->assertGreaterThan(0, $policy->varianceRules()->count());

        $errors = app(PricingPolicyValidator::class)->validateRateConfiguration($policy);
        $this->assertSame([], $errors);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PricingPolicySeeder::class);
        $this->seed(PricingPolicySeeder::class);

        $this->assertSame(1, PricingPolicy::query()->where('name', PricingPolicySeeder::POLICY_NAME)->count());
        $this->assertSame(1, PricingPolicy::query()->count());
    }

    public function test_pricing_engine_can_resolve_and_calculate_seeded_active_policy(): void
    {
        $this->seed(PricingPolicySeeder::class);

        $policy = app(PricingPolicyResolver::class)->resolveActive();
        $this->assertSame(PricingPolicySeeder::POLICY_NAME, $policy->name);

        $input = app(PricingInputFactory::class)->create(
            distance: 250,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10,
            shipmentPriority: 'standard',
            customerType: CustomerType::STANDARD,
        );

        $breakdown = app(PricingEngine::class)->calculate($input, $policy);

        $this->assertSame($policy->id, $breakdown->pricingPolicyId);
        $this->assertGreaterThan(0, $breakdown->finalTotal);
        $this->assertSame('USD', $breakdown->currency);
    }

    public function test_seeder_does_not_create_a_second_active_policy_when_one_already_resolves(): void
    {
        PricingPolicy::create([
            'name' => 'Existing Active',
            'status' => PricingPolicyStatus::ACTIVE,
            'priority' => 5,
            'currency' => 'USD',
            'tax_rate' => 8,
        ]);

        $this->seed(PricingPolicySeeder::class);

        $bootstrap = PricingPolicy::query()->where('name', PricingPolicySeeder::POLICY_NAME)->firstOrFail();
        $this->assertSame(PricingPolicyStatus::DRAFT, $bootstrap->status);

        $resolved = app(PricingPolicyResolver::class)->resolveActive();
        $this->assertSame('Existing Active', $resolved->name);
    }
}
