<?php

namespace Tests\Unit\Routing;

use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlannedRouteCacheImmutabilityTest extends TestCase
{
    private PlannedRouteCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->cache = new PlannedRouteCache();
    }

    public function test_store_if_absent_freezes_route_and_refuses_overwrite(): void
    {
        $stored = $this->cache->storeIfAbsent(
            shipmentId: 'ship-1',
            coordinates: [
                ['latitude' => 1.0, 'longitude' => 2.0],
                ['latitude' => 3.0, 'longitude' => 4.0],
            ],
            destination: ['latitude' => 3.0, 'longitude' => 4.0],
            origin: ['latitude' => 1.0, 'longitude' => 2.0],
            apiPayload: ['route' => ['id' => 'route-a', 'geometry' => ['coordinates' => [
                ['latitude' => 1.0, 'longitude' => 2.0],
                ['latitude' => 3.0, 'longitude' => 4.0],
            ]]]],
            provider: 'graphhopper',
            isReroute: false,
        );

        $this->assertTrue($stored);
        $this->assertSame('route-a', $this->cache->get('ship-1')['api_payload']['route']['id']);

        $refused = $this->cache->storeIfAbsent(
            shipmentId: 'ship-1',
            coordinates: [
                ['latitude' => 9.0, 'longitude' => 9.0],
                ['latitude' => 8.0, 'longitude' => 8.0],
            ],
            destination: ['latitude' => 8.0, 'longitude' => 8.0],
            origin: ['latitude' => 9.0, 'longitude' => 9.0],
            apiPayload: ['route' => ['id' => 'route-b', 'geometry' => ['coordinates' => [
                ['latitude' => 9.0, 'longitude' => 9.0],
                ['latitude' => 8.0, 'longitude' => 8.0],
            ]]]],
            provider: 'simulator',
            isReroute: false,
        );

        $this->assertFalse($refused);
        $this->assertSame('route-a', $this->cache->get('ship-1')['api_payload']['route']['id']);
        $this->assertSame('graphhopper', $this->cache->get('ship-1')['provider']);
    }

    public function test_replace_is_authorized_overwrite_for_reroute(): void
    {
        $this->cache->storeIfAbsent(
            shipmentId: 'ship-2',
            coordinates: [
                ['latitude' => 1.0, 'longitude' => 2.0],
                ['latitude' => 3.0, 'longitude' => 4.0],
            ],
            destination: ['latitude' => 3.0, 'longitude' => 4.0],
            origin: ['latitude' => 1.0, 'longitude' => 2.0],
            apiPayload: ['route' => ['id' => 'route-old', 'geometry' => ['coordinates' => [
                ['latitude' => 1.0, 'longitude' => 2.0],
                ['latitude' => 3.0, 'longitude' => 4.0],
            ]]]],
            provider: 'graphhopper',
        );

        Cache::forever('simulation:route_geometry:ship-2', ['route_id' => 'route-old']);
        Cache::forever('simulation:route_progress:ship-2', ['route_id' => 'route-old']);

        $this->cache->replace(
            shipmentId: 'ship-2',
            coordinates: [
                ['latitude' => 5.0, 'longitude' => 6.0],
                ['latitude' => 7.0, 'longitude' => 8.0],
            ],
            destination: ['latitude' => 7.0, 'longitude' => 8.0],
            origin: ['latitude' => 5.0, 'longitude' => 6.0],
            apiPayload: ['route' => ['id' => 'route-new', 'geometry' => ['coordinates' => [
                ['latitude' => 5.0, 'longitude' => 6.0],
                ['latitude' => 7.0, 'longitude' => 8.0],
            ]]]],
            provider: 'graphhopper',
            isReroute: true,
        );

        $this->assertSame('route-new', $this->cache->get('ship-2')['api_payload']['route']['id']);
        $this->assertTrue((bool) $this->cache->get('ship-2')['is_reroute']);
        $this->assertNull(Cache::get('simulation:route_geometry:ship-2'));
        $this->assertNull(Cache::get('simulation:route_progress:ship-2'));
    }
}
