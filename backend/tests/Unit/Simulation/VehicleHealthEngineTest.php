<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Driver\DriverProfile;
use App\Simulation\Health\VehicleHealthEngine;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class VehicleHealthEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_fuel_decreases_while_moving_with_speed(): void
    {
        Log::spy();
        $engine = new VehicleHealthEngine();
        $profile = VehicleProfile::truck(4, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);

        Cache::put('simulation:vehicle_speed:vh1', 60.0, 3600);

        $first = $engine->compute('vh1', $profile, DriverProfile::Normal, 'moving', 0.4, 90.0, 60.0);
        $second = $engine->compute('vh1', $profile, DriverProfile::Normal, 'moving', 0.4, 90.0, 60.0);

        $this->assertTrue($first->fuelLevel > 0);
        $this->assertTrue($second->fuelLevel < $first->fuelLevel);
        $this->assertTrue($first->fuelConsumptionRate > 0);
        $this->assertTrue(Cache::has(VehicleHealthEngine::CACHE_PREFIX . 'vh1'));
    }

    public function test_engine_temperature_evolves_smoothly(): void
    {
        Log::spy();
        $engine = new VehicleHealthEngine();
        $profile = VehicleProfile::truck(4, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);
        Cache::put('simulation:vehicle_speed:vh2', 70.0, 3600);

        $temps = [];
        for ($i = 0; $i < 8; $i++) {
            $snap = $engine->compute('vh2', $profile, DriverProfile::Aggressive, 'moving', 0.7, 90.0, 60.0);
            $temps[] = $snap->engineTemperature;
        }

        for ($i = 1; $i < count($temps); $i++) {
            $this->assertTrue(abs($temps[$i] - $temps[$i - 1]) <= 2.6);
        }
    }

    public function test_poor_health_reduces_recommended_max_speed_and_accel_scale(): void
    {
        Log::spy();
        $engine = new VehicleHealthEngine();
        $profile = VehicleProfile::truck(4, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);

        $healthy = $engine->compute('vh3', $profile, DriverProfile::Eco, 'moving', 0.2, 90.0, 60.0);

        Cache::put(VehicleHealthEngine::CACHE_PREFIX . 'vh4', [
            'vehicle_id' => 'vh4',
            'fuel_level' => 50,
            'fuel_consumption_rate' => 30,
            'engine_temperature' => 108,
            'engine_health' => 35,
            'battery_level' => 80,
            'brake_wear' => 40,
            'tyre_wear' => 40,
            'maintenance_score' => 40,
            'operational_status' => 'critical',
            'recommended_max_speed' => 90,
            'acceleration_scale' => 1,
            'last_acceleration_ms2' => 0.05,
            'timestamp' => now()->toIso8601String(),
        ], 3600);

        $poor = $engine->compute('vh4', $profile, DriverProfile::Normal, 'moving', 0.5, 90.0, 60.0);

        $this->assertTrue($poor->recommendedMaxSpeed < $healthy->recommendedMaxSpeed);
        $this->assertTrue($poor->accelerationScale < 1.0);
        $this->assertContains($poor->operationalStatus, ['degraded', 'critical', 'unavailable']);
    }

    public function test_constraints_with_operational_limits_derate_speed_and_accel(): void
    {
        $profile = VehicleProfile::truck(4, 2.55, 16.5, 40000, 90, true, VehicleProfile::FREIGHT_HEAVY_TRUCK);
        $base = \App\Simulation\Dynamics\VehicleConstraints::fromVehicleProfile($profile);
        $derated = $base->withOperationalLimits(40.0, 0.5);

        $this->assertTrue($derated->maxSpeedKmh <= 40.0);
        $this->assertTrue($derated->maxAccelerationMs2 <= $base->maxAccelerationMs2 * 0.5 + 1e-9);
    }
}
