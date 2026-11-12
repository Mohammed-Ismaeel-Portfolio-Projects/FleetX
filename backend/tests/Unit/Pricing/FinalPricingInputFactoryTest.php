<?php

namespace Tests\Unit\Pricing;

use App\Enums\CustomerType;
use App\Enums\OperationalActualsCaptureSource;
use App\Enums\PricingPolicyStatus;
use App\Models\PricingPolicy;
use App\Models\ShipmentOperationalActuals;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderPriceBreakdown;
use App\Services\Pricing\FinalPricingInputFactory;
use App\Services\Pricing\Exceptions\ShipmentFinalPricingException;
use App\Services\Pricing\PricingBreakdownSerializer;
use App\Services\Pricing\PricingEngine;
use App\Services\Pricing\PricingInputFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFinalPricingFixtures;
use Tests\TestCase;

class FinalPricingInputFactoryTest extends TestCase
{
    use CreatesFinalPricingFixtures;
    use RefreshDatabase;

    private FinalPricingInputFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FinalPricingInputFactory(
            new PricingInputFactory(),
            app(\App\Services\Pricing\ServiceCatalogResolver::class),
        );
    }

    public function test_build_baseline_input_from_submission_snapshot(): void
    {
        $policy = $this->createFullPricingPolicy();
        $order = ShipmentOrder::factory()->create();
        $snapshot = $this->persistSubmissionSnapshot($order, $policy);

        $input = $this->factory->buildBaselineInput($snapshot);

        $this->assertSame(500.0, $input->distance);
        $this->assertSame(15.0, $input->cargoWeight);
    }

    public function test_build_actual_input_uses_operational_actuals(): void
    {
        $policy = $this->createFullPricingPolicy();
        $order = ShipmentOrder::factory()->create();
        $snapshot = $this->persistSubmissionSnapshot($order, $policy);

        $actuals = new ShipmentOperationalActuals([
            'actual_distance_km' => 575,
            'actual_cargo_weight' => 18,
            'actual_loading_wait_minutes' => 45,
            'actual_unloading_wait_minutes' => 60,
            'capture_source' => OperationalActualsCaptureSource::OPS_MANUAL,
            'captured_at' => now(),
        ]);

        $input = $this->factory->buildActualInput($snapshot, $actuals, $policy);

        $this->assertSame(575.0, $input->distance);
        $this->assertSame(18.0, $input->cargoWeight);
        $this->assertSame(45, $input->loadingWaitingTime);
        $this->assertSame(60, $input->unloadingWaitingTime);
    }

    public function test_missing_distance_throws_explicit_error(): void
    {
        $policy = $this->createFullPricingPolicy();
        $order = ShipmentOrder::factory()->create();
        $snapshot = $this->persistSubmissionSnapshot($order, $policy);

        $actuals = new ShipmentOperationalActuals([
            'actual_cargo_weight' => 15,
            'actual_loading_wait_minutes' => 0,
            'actual_unloading_wait_minutes' => 0,
        ]);

        $this->expectException(ShipmentFinalPricingException::class);
        $this->expectExceptionMessage('distance');

        $this->factory->buildActualInput($snapshot, $actuals, $policy);
    }

    public function test_does_not_silently_default_missing_actuals_to_submission_values(): void
    {
        $policy = $this->createFullPricingPolicy();
        $order = ShipmentOrder::factory()->create();
        $snapshot = $this->persistSubmissionSnapshot($order, $policy);

        $actuals = new ShipmentOperationalActuals([
            'actual_distance_km' => 575,
            'actual_cargo_weight' => null,
            'actual_loading_wait_minutes' => 0,
            'actual_unloading_wait_minutes' => 0,
        ]);

        try {
            $this->factory->buildActualInput($snapshot, $actuals, $policy);
            $this->fail('Expected ShipmentFinalPricingException was not thrown.');
        } catch (ShipmentFinalPricingException $e) {
            $this->assertSame('MISSING_ACTUAL_WEIGHT', $e->errorCode);
        }
    }
}
