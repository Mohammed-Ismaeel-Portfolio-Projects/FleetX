<?php

namespace Tests\Unit\Support;

use App\Support\DriverMotionKinematics;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * D15-B — live longitudinal kinematics (km/h → m/s → a_obs).
 */
class DriverMotionKinematicsTest extends TestCase
{
    public function test_kmh_to_meters_per_second(): void
    {
        $this->assertEqualsWithDelta(10.0, DriverMotionKinematics::kmhToMetersPerSecond(36.0), 1e-9);
        $this->assertEqualsWithDelta(0.0, DriverMotionKinematics::kmhToMetersPerSecond(0.0), 1e-9);
    }

    public function test_meters_per_second_to_kmh(): void
    {
        $this->assertEqualsWithDelta(36.0, DriverMotionKinematics::metersPerSecondToKmh(10.0), 1e-9);
    }

    public function test_conversion_round_trip(): void
    {
        $kmh = 80.0;
        $ms = DriverMotionKinematics::kmhToMetersPerSecond($kmh);
        $this->assertEqualsWithDelta($kmh, DriverMotionKinematics::metersPerSecondToKmh($ms), 1e-9);
    }

    public function test_deceleration_is_negative(): void
    {
        $obs = DriverMotionKinematics::observe(72.0, 36.0, 5.0);
        $this->assertNotNull($obs);
        $this->assertTrue($obs->isDeceleration());
        $this->assertFalse($obs->isAcceleration());
        // 72 km/h = 20 m/s; 36 km/h = 10 m/s; Δt = 5 s → a = (10-20)/5 = -2.0 m/s²
        $this->assertEqualsWithDelta(-2.0, $obs->accelerationMs2, 1e-9);
        $this->assertEqualsWithDelta(2.0, $obs->decelerationMagnitudeMs2(), 1e-9);
    }

    public function test_acceleration_is_positive(): void
    {
        $obs = DriverMotionKinematics::observe(36.0, 72.0, 5.0);
        $this->assertNotNull($obs);
        $this->assertTrue($obs->isAcceleration());
        $this->assertEqualsWithDelta(2.0, $obs->accelerationMs2, 1e-9);
        $this->assertEqualsWithDelta(0.0, $obs->decelerationMagnitudeMs2(), 1e-9);
    }

    public function test_zero_delta_time_is_rejected(): void
    {
        $this->assertNull(DriverMotionKinematics::observe(80.0, 40.0, 0.0));
    }

    public function test_negative_delta_time_is_rejected(): void
    {
        $this->assertNull(DriverMotionKinematics::observe(80.0, 40.0, -5.0));
    }

    public function test_non_finite_speed_is_rejected(): void
    {
        $this->assertNull(DriverMotionKinematics::observe(INF, 40.0, 5.0));
        $this->assertNull(DriverMotionKinematics::observe(40.0, NAN, 5.0));
    }

    public function test_elapsed_seconds_uses_absolute_carbon3_diff(): void
    {
        $earlier = Carbon::parse('2026-08-17 12:00:00');
        $later = Carbon::parse('2026-08-17 12:00:05');

        $this->assertEqualsWithDelta(5.0, DriverMotionKinematics::elapsedSeconds($earlier, $later), 1e-9);
        $this->assertEqualsWithDelta(5.0, DriverMotionKinematics::elapsedSeconds($later, $earlier), 1e-9);
    }

    public function test_duplicate_timestamps_yield_zero_elapsed(): void
    {
        $t = Carbon::parse('2026-08-17 12:00:00');
        $this->assertEqualsWithDelta(0.0, DriverMotionKinematics::elapsedSeconds($t, $t), 1e-9);
        $this->assertNull(DriverMotionKinematics::observe(80.0, 10.0, 0.0));
    }

    public function test_unparseable_timestamp_returns_null(): void
    {
        $this->assertNull(DriverMotionKinematics::elapsedSeconds('not-a-date', '2026-08-17 12:00:00'));
        $this->assertNull(DriverMotionKinematics::elapsedSeconds(null, '2026-08-17 12:00:00'));
        $this->assertNull(DriverMotionKinematics::elapsedSeconds('', '2026-08-17 12:00:00'));
    }

    public function test_stopping_distance_formula(): void
    {
        // v = 36 km/h = 10 m/s; a = 2 m/s² → s = 100 / 4 = 25 m
        $this->assertEqualsWithDelta(
            25.0,
            DriverMotionKinematics::stoppingDistanceMeters(36.0, 2.0),
            1e-9
        );
        $this->assertNull(DriverMotionKinematics::stoppingDistanceMeters(36.0, 0.0));
        $this->assertNull(DriverMotionKinematics::stoppingDistanceMeters(36.0, -1.0));
    }
}
