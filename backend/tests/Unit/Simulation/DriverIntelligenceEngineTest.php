<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Driver\DriverIntelligenceEngine;
use App\Simulation\Driver\DriverProfile;
use App\Simulation\Environment\EnvironmentSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DriverIntelligenceEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_profiles_produce_different_desired_speeds_under_identical_environment(): void
    {
        Log::spy();
        $engine = new DriverIntelligenceEngine();
        $env = $this->makeEnvironment(recommended: 70.0, limit: 90.0);

        $results = [];
        foreach (DriverProfile::cases() as $profile) {
            Cache::flush();
            $decision = $engine->decide('v-' . $profile->value, $profile, $env, 'moving');
            $results[$profile->value] = $decision->desiredTargetSpeed;
            $this->assertSame(70.0, $decision->environmentRecommendedSpeed);
        }

        $this->assertTrue($results['eco'] < $results['normal']);
        $this->assertTrue($results['defensive'] < $results['normal']);
        $this->assertTrue($results['aggressive'] > $results['normal']);
        $this->assertTrue($results['eco'] <= 70.0);
        $this->assertTrue($results['aggressive'] <= 90.0 * 1.05 + 0.01);
    }

    public function test_defensive_slows_further_in_adverse_conditions(): void
    {
        Log::spy();
        $engine = new DriverIntelligenceEngine();

        $clear = $this->makeEnvironment(70.0, 90.0, 'clear', 'dry');
        $adverse = $this->makeEnvironment(70.0, 90.0, 'snow', 'icy', accident: true);

        Cache::flush();
        $a = $engine->decide('v-def', DriverProfile::Defensive, $clear, 'moving');
        Cache::flush();
        $b = $engine->decide('v-def', DriverProfile::Defensive, $adverse, 'moving');

        $this->assertTrue($b->desiredTargetSpeed < $a->desiredTargetSpeed);
    }

    public function test_eco_never_exceeds_road_limit(): void
    {
        Log::spy();
        $engine = new DriverIntelligenceEngine();
        $env = $this->makeEnvironment(recommended: 100.0, limit: 80.0);

        $decision = $engine->decide('v-eco', DriverProfile::Eco, $env, 'moving');
        $this->assertTrue($decision->desiredTargetSpeed <= 80.0);
        $this->assertTrue(Cache::has(DriverIntelligenceEngine::CACHE_PREFIX . 'v-eco'));
    }

    private function makeEnvironment(
        float $recommended,
        float $limit,
        string $weather = 'clear',
        string $road = 'dry',
        bool $accident = false,
    ): EnvironmentSnapshot {
        return new EnvironmentSnapshot(
            routeId: 'r1',
            vehicleId: 'v1',
            timestamp: now()->toIso8601String(),
            roadSpeedLimitKmh: $limit,
            trafficDensity: 0.4,
            trafficFactor: 0.74,
            weatherCondition: $weather,
            roadCondition: $road,
            roadEvent: null,
            constructionZone: false,
            accidentNearby: $accident,
            roadClosed: false,
            schoolZone: false,
            tollGateAhead: false,
            borderCrossingAhead: false,
            recommendedTargetSpeedKmh: $recommended,
        );
    }
}
