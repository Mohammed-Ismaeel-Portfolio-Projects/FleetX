<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Environment\EnvironmentEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class EnvironmentEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_traffic_density_evolves_smoothly_across_ticks(): void
    {
        Log::spy();
        $engine = new EnvironmentEngine();

        $prev = null;
        $densities = [];

        for ($tick = 0; $tick < 12; $tick++) {
            $snapshot = $engine->compute('veh-env-1', 'route-a', $tick);
            $densities[] = $snapshot->trafficDensity;

            if ($prev !== null) {
                $this->assertTrue(
                    abs($snapshot->trafficDensity - $prev) <= 0.08 + 1e-6,
                    "traffic jumped too much between ticks: {$prev} → {$snapshot->trafficDensity}"
                );
            }
            $prev = $snapshot->trafficDensity;
        }

        $this->assertTrue(max($densities) > min($densities), 'traffic should vary over time');
        $this->assertTrue(Cache::has(EnvironmentEngine::CACHE_PREFIX . 'veh-env-1'));
    }

    public function test_same_inputs_are_deterministic(): void
    {
        Log::spy();
        Cache::flush();
        $engine = new EnvironmentEngine();

        $a = $engine->compute('veh-det', 'route-x', 3);
        Cache::flush();
        $b = $engine->compute('veh-det', 'route-x', 3);

        // Without previous density, first tick of same seed/tick matches.
        $this->assertSame($a->roadSpeedLimitKmh, $b->roadSpeedLimitKmh);
        $this->assertSame($a->trafficDensity, $b->trafficDensity);
        $this->assertSame($a->recommendedTargetSpeedKmh, $b->recommendedTargetSpeedKmh);
    }

    public function test_recommended_speed_respects_road_limit_and_events(): void
    {
        Log::spy();
        $engine = new EnvironmentEngine();
        $snapshot = $engine->compute('veh-speed', 'route-y', 0);

        $this->assertTrue($snapshot->recommendedTargetSpeedKmh <= $snapshot->roadSpeedLimitKmh);
        $this->assertTrue($snapshot->recommendedTargetSpeedKmh >= 0.0);
        $this->assertTrue($snapshot->trafficFactor > 0.0 && $snapshot->trafficFactor <= 1.0);
    }
}
