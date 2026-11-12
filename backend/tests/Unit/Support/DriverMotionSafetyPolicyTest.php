<?php

namespace Tests\Unit\Support;

use App\Models\Assignment;
use App\Models\Vehicle;
use App\Simulation\Dynamics\VehicleConstraints;
use App\Simulation\Routing\Application\Factories\VehicleProfileFactory;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Support\DriverMotionKinematics;
use App\Support\DriverMotionSafetyPolicy;
use App\Support\DriverSpeedSafetyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D15-B — live motion policy, freight-class context, simulation-threshold isolation.
 */
class DriverMotionSafetyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_can_be_disabled(): void
    {
        config(['driver_safety.motion.enabled' => false]);
        $this->assertFalse(DriverMotionSafetyPolicy::enabled());

        config(['driver_safety.motion.enabled' => true]);
        $this->assertTrue(DriverMotionSafetyPolicy::enabled());
    }

    public function test_freight_class_normalization_reuses_vehicle_profile_factory(): void
    {
        $this->assertSame(
            VehicleProfileFactory::normalizeFreightClass('van', 0),
            DriverMotionSafetyPolicy::normalizeFreightClass('van', 0)
        );
        $this->assertSame(VehicleProfile::FREIGHT_VAN, DriverMotionSafetyPolicy::normalizeFreightClass('VAN', 0));
        $this->assertSame(VehicleProfile::FREIGHT_LIGHT_TRUCK, DriverMotionSafetyPolicy::normalizeFreightClass('light_truck', 0));
        $this->assertSame(VehicleProfile::FREIGHT_HEAVY_TRUCK, DriverMotionSafetyPolicy::normalizeFreightClass('heavy_truck', 0));
        $this->assertSame(VehicleProfile::FREIGHT_VAN, DriverMotionSafetyPolicy::normalizeFreightClass(null, 3000));
        $this->assertSame(VehicleProfile::FREIGHT_LIGHT_TRUCK, DriverMotionSafetyPolicy::normalizeFreightClass(null, 8000));
        $this->assertSame(VehicleProfile::FREIGHT_HEAVY_TRUCK, DriverMotionSafetyPolicy::normalizeFreightClass(null, 40000));
    }

    public function test_class_specific_harsh_brake_thresholds(): void
    {
        $van = DriverMotionSafetyPolicy::harshBrakeThresholdMs2(VehicleProfile::FREIGHT_VAN);
        $light = DriverMotionSafetyPolicy::harshBrakeThresholdMs2(VehicleProfile::FREIGHT_LIGHT_TRUCK);
        $heavy = DriverMotionSafetyPolicy::harshBrakeThresholdMs2(VehicleProfile::FREIGHT_HEAVY_TRUCK);

        $this->assertEqualsWithDelta(2.5, $van, 1e-9);
        $this->assertEqualsWithDelta(2.0, $light, 1e-9);
        $this->assertEqualsWithDelta(1.5, $heavy, 1e-9);
        $this->assertGreaterThan($light, $van);
        $this->assertGreaterThan($heavy, $light);
    }

    public function test_live_thresholds_are_not_simulation_vehicle_constraints(): void
    {
        $simVan = VehicleConstraints::fromVehicleProfile(VehicleProfile::truck(
            height: 2.6, width: 2.1, length: 5.8, weight: 3200,
            freightClass: VehicleProfile::FREIGHT_VAN
        ));
        $simLight = VehicleConstraints::fromVehicleProfile(VehicleProfile::truck(
            height: 3.0, width: 2.35, length: 8.2, weight: 7500,
            freightClass: VehicleProfile::FREIGHT_LIGHT_TRUCK
        ));
        $simHeavy = VehicleConstraints::fromVehicleProfile(VehicleProfileFactory::defaultTruck());

        $this->assertEqualsWithDelta(0.22, $simVan->maxBrakingMs2, 1e-9);
        $this->assertEqualsWithDelta(0.18, $simLight->maxBrakingMs2, 1e-9);
        $this->assertEqualsWithDelta(0.15, $simHeavy->maxBrakingMs2, 1e-9);

        foreach ([
            VehicleProfile::FREIGHT_VAN => $simVan,
            VehicleProfile::FREIGHT_LIGHT_TRUCK => $simLight,
            VehicleProfile::FREIGHT_HEAVY_TRUCK => $simHeavy,
        ] as $class => $sim) {
            $live = DriverMotionSafetyPolicy::harshBrakeThresholdMs2($class);
            $this->assertNotEquals($sim->maxBrakingMs2, $live);
            $this->assertNotEquals($sim->maxAccelerationMs2, $live);
            $this->assertGreaterThan(1.0, $live);
            $this->assertGreaterThan($sim->maxBrakingMs2 * 5, $live);
        }
    }

    public function test_d14_speed_policy_remains_unchanged(): void
    {
        $this->assertTrue(DriverSpeedSafetyPolicy::enabled());
        $this->assertEqualsWithDelta(110.0, DriverSpeedSafetyPolicy::thresholdKmh(), 1e-9);
        $this->assertSame(3, DriverSpeedSafetyPolicy::minSamples());
        $this->assertSame(10, DriverSpeedSafetyPolicy::minDurationSeconds());
        $this->assertSame(300, DriverSpeedSafetyPolicy::cooldownSeconds());
        $this->assertEqualsWithDelta(95.0, DriverSpeedSafetyPolicy::recoveryKmh(), 1e-9);
    }

    public function test_usable_interval_rejects_duplicate_and_implausible_dt(): void
    {
        $this->assertFalse(DriverMotionSafetyPolicy::isUsableInterval(null));
        $this->assertFalse(DriverMotionSafetyPolicy::isUsableInterval(0.0));
        $this->assertFalse(DriverMotionSafetyPolicy::isUsableInterval(1.0));
        $this->assertFalse(DriverMotionSafetyPolicy::isUsableInterval(2.9));
        $this->assertTrue(DriverMotionSafetyPolicy::isUsableInterval(3.0));
        $this->assertTrue(DriverMotionSafetyPolicy::isUsableInterval(5.0));
        $this->assertTrue(DriverMotionSafetyPolicy::isUsableInterval(15.0));
        $this->assertFalse(DriverMotionSafetyPolicy::isUsableInterval(15.1));
        $this->assertFalse(DriverMotionSafetyPolicy::isUsableInterval(60.0));
        $this->assertFalse(DriverMotionSafetyPolicy::isUsableInterval(NAN));
    }

    public function test_missing_speed_is_not_usable(): void
    {
        $this->assertFalse(DriverMotionSafetyPolicy::hasUsableSpeed(null));
        $this->assertFalse(DriverMotionSafetyPolicy::hasUsableSpeed(''));
        $this->assertFalse(DriverMotionSafetyPolicy::hasUsableSpeed('fast'));
        $this->assertTrue(DriverMotionSafetyPolicy::hasUsableSpeed(0));
        $this->assertTrue(DriverMotionSafetyPolicy::hasUsableSpeed(42.5));
    }

    public function test_live_driver_evidence_requires_received_at(): void
    {
        $this->assertFalse(DriverMotionSafetyPolicy::isLiveDriverEvidence(null));
        $this->assertFalse(DriverMotionSafetyPolicy::isLiveDriverEvidence(''));
        $this->assertTrue(DriverMotionSafetyPolicy::isLiveDriverEvidence('2026-08-17 12:00:00'));
        $this->assertTrue(DriverMotionSafetyPolicy::isLiveDriverEvidence(now()));
    }

    public function test_implausible_deceleration_is_rejected(): void
    {
        $this->assertTrue(DriverMotionSafetyPolicy::isPhysicallyPlausibleDeceleration(2.0));
        $this->assertTrue(DriverMotionSafetyPolicy::isPhysicallyPlausibleDeceleration(8.0));
        $this->assertFalse(DriverMotionSafetyPolicy::isPhysicallyPlausibleDeceleration(8.01));
        $this->assertFalse(DriverMotionSafetyPolicy::isPhysicallyPlausibleDeceleration(-1.0));
    }

    public function test_gps_noise_scale_is_below_harsh_thresholds(): void
    {
        // ±5 km/h over D6 5 s ≈ 0.28 m/s² — must not meet any class threshold.
        $noise = DriverMotionKinematics::observe(80.0, 75.0, 5.0);
        $this->assertNotNull($noise);
        $this->assertLessThan(
            DriverMotionSafetyPolicy::harshBrakeThresholdMs2(VehicleProfile::FREIGHT_HEAVY_TRUCK),
            $noise->decelerationMagnitudeMs2()
        );
    }

    public function test_configured_interval_is_required_before_observe_is_usable(): void
    {
        $dt = DriverMotionKinematics::elapsedSeconds(
            now(),
            now()->copy()->addSeconds(5)
        );
        $this->assertTrue(DriverMotionSafetyPolicy::isUsableInterval($dt));

        $dup = DriverMotionKinematics::elapsedSeconds(now(), now());
        $this->assertFalse(DriverMotionSafetyPolicy::isUsableInterval($dup));
    }

    public function test_sudden_stop_primitives_are_ordered(): void
    {
        $this->assertGreaterThan(
            DriverMotionSafetyPolicy::suddenStopStoppedMaxKmh(),
            DriverMotionSafetyPolicy::suddenStopMovingMinKmh()
        );
        $this->assertEqualsWithDelta(30.0, DriverMotionSafetyPolicy::suddenStopMovingMinKmh(), 1e-9);
        $this->assertEqualsWithDelta(5.0, DriverMotionSafetyPolicy::suddenStopStoppedMaxKmh(), 1e-9);
        $this->assertEqualsWithDelta(25.0, DriverMotionSafetyPolicy::suddenStopMinSpeedDropKmh(), 1e-9);
    }

    public function test_freight_class_from_assigned_vehicle_not_client_payload(): void
    {
        $van = Vehicle::factory()->create([
            'routing_class' => Vehicle::ROUTING_CLASS_VAN,
            'gross_weight_kg' => 3200,
        ]);
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $van->id,
        ]);

        $this->assertSame(
            VehicleProfile::FREIGHT_VAN,
            DriverMotionSafetyPolicy::freightClassForAssignment($assignment->fresh(['vehicle', 'trailer']))
        );
    }

    public function test_confirmation_defaults_match_d6_sampling(): void
    {
        $this->assertSame(3, DriverMotionSafetyPolicy::minSamples());
        $this->assertSame(10, DriverMotionSafetyPolicy::minDurationSeconds());
        $this->assertSame(300, DriverMotionSafetyPolicy::cooldownSeconds());
    }
}
