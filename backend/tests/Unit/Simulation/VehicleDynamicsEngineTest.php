<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Dynamics\AccelerationModel;
use App\Simulation\Dynamics\BrakingModel;
use App\Simulation\Dynamics\SpeedController;
use App\Simulation\Dynamics\VehicleConstraints;
use App\Simulation\Dynamics\VehicleDynamicsEngine;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class VehicleDynamicsEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_accelerates_smoothly_from_standstill_without_jumping_to_cruise(): void
    {
        Log::spy();

        $engine = $this->makeEngine();
        $profile = VehicleProfile::truck(4, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);

        $tick1 = $engine->computeTick('v1', 'moving', $profile, 60.0, 500_000);
        $tick2 = $engine->computeTick('v1', 'moving', $profile, 60.0, 500_000);
        $tick3 = $engine->computeTick('v1', 'moving', $profile, 60.0, 500_000);

        $this->assertTrue($tick1->currentSpeedKmh > 0.0);
        $this->assertTrue($tick1->currentSpeedKmh < 40.0, 'must not reach cruise in one 60s tick');
        $this->assertTrue($tick2->currentSpeedKmh >= $tick1->currentSpeedKmh);
        $this->assertTrue($tick3->currentSpeedKmh >= $tick2->currentSpeedKmh);
        $this->assertTrue($tick3->currentSpeedKmh < 80.0, 'still ramping after 3 ticks');
        $this->assertTrue($tick1->distanceMeters > 0.0);
        $this->assertTrue($tick1->accelerationMs2 > 0.0);
    }

    public function test_brakes_smoothly_toward_zero_without_instant_stop(): void
    {
        Log::spy();

        $engine = $this->makeEngine();
        $profile = VehicleProfile::truck(4, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);

        for ($i = 0; $i < 12; $i++) {
            $engine->computeTick('v2', 'moving', $profile, 60.0, 500_000);
        }

        $moving = $engine->computeTick('v2', 'moving', $profile, 60.0, 500_000);
        $this->assertTrue($moving->currentSpeedKmh > 20.0);

        $braking = $engine->computeTick('v2', 'idle', $profile, 60.0, 500_000);
        $this->assertTrue($braking->currentSpeedKmh < $moving->currentSpeedKmh);
        $this->assertTrue($braking->currentSpeedKmh > 0.0, 'must not instant-stop');
        $this->assertTrue($braking->accelerationMs2 < 0.0);
    }

    public function test_constraints_respect_heavy_truck_max_speed(): void
    {
        $profile = VehicleProfile::truck(4, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);
        $constraints = VehicleConstraints::fromVehicleProfile($profile);

        $this->assertSame(VehicleProfile::FREIGHT_HEAVY_TRUCK, $constraints->category);
        $this->assertTrue($constraints->maxSpeedKmh <= 90.0);
        $this->assertTrue($constraints->cruiseSpeedKmh <= $constraints->maxSpeedKmh);
        $this->assertTrue($constraints->maxAccelerationMs2 > 0.0);
        $this->assertTrue($constraints->maxBrakingMs2 > 0.0);
        $this->assertTrue($constraints->maxAccelerationMs2 < 0.5, 'tick-calibrated accel');
    }

    public function test_uses_environment_recommended_target_instead_of_fixed_cruise(): void
    {
        Log::spy();
        $engine = $this->makeEngine();
        $profile = VehicleProfile::truck(4, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);

        // Low environment target — dynamics must aim for it, not full cruise.
        $tick = $engine->computeTick('v-env', 'moving', $profile, 60.0, 500_000, 40.0);
        $this->assertSame(40.0, $tick->targetSpeedKmh);

        // Ramp until near recommended; should not exceed it meaningfully.
        for ($i = 0; $i < 20; $i++) {
            $tick = $engine->computeTick('v-env', 'moving', $profile, 60.0, 500_000, 40.0);
        }
        $this->assertTrue($tick->currentSpeedKmh <= 40.0 + 0.05);
        $this->assertTrue($tick->currentSpeedKmh > 30.0);
    }

    private function makeEngine(): VehicleDynamicsEngine
    {
        return new VehicleDynamicsEngine(
            new SpeedController(new AccelerationModel(), new BrakingModel())
        );
    }
}
