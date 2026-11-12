<?php

namespace Tests\Unit\TrackingSimulator;

use App\Services\TrackingSimulator\EuropeanFleetLocations;
use Tests\TestCase;

class EuropeanFleetLocationsTest extends TestCase
{
    private EuropeanFleetLocations $fleet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fleet = new EuropeanFleetLocations();
    }

    public function test_all_hubs_are_valid_eu_land_positions(): void
    {
        foreach ($this->fleet->allLocations() as $location) {
            $this->assertTrue(
                $this->fleet->validateVehicleLocation(
                    (float) $location['latitude'],
                    (float) $location['longitude']
                ),
                "Hub {$location['city']} failed EU land validation"
            );
        }
    }

    public function test_distributed_points_are_always_valid(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $point = $this->fleet->pickDistributedLogisticsPoint();
            $this->assertTrue(
                $this->fleet->validateVehicleLocation($point['latitude'], $point['longitude']),
                "Distributed point failed: {$point['latitude']},{$point['longitude']}"
            );
        }
    }

    public function test_rejects_ocean_and_non_eu_coordinates(): void
    {
        // Mid-Atlantic
        $this->assertFalse($this->fleet->validateVehicleLocation(40.0, -40.0));
        // Arctic
        $this->assertFalse($this->fleet->validateVehicleLocation(80.0, 20.0));
        // New York
        $this->assertFalse($this->fleet->validateVehicleLocation(40.7128, -74.0060));
        // Central Mediterranean open water
        $this->assertFalse($this->fleet->validateVehicleLocation(38.0, 10.0));
    }

    public function test_ensure_valid_snaps_invalid_coords_to_hub(): void
    {
        $safe = $this->fleet->ensureValidPosition(40.7128, -74.0060);
        $this->assertTrue($this->fleet->validateVehicleLocation($safe['latitude'], $safe['longitude']));
    }

    public function test_land_path_avoids_direct_sea_shortcut_for_barcelona_rome(): void
    {
        $path = $this->fleet->buildLandPath('Barcelona', 'Rome');
        $this->assertGreaterThanOrEqual(3, count($path), 'Expected intermediate land hubs');

        foreach ($path as $point) {
            $this->assertTrue(
                $this->fleet->validateVehicleLocation($point['latitude'], $point['longitude'])
            );
        }
    }

    public function test_shipment_pairs_use_eu_cities_only(): void
    {
        $cities = array_map(fn ($l) => $l['city'], $this->fleet->allLocations());

        for ($i = 0; $i < 50; $i++) {
            $pair = $this->fleet->randomShipmentPair();
            $this->assertContains($pair['pickup_city'], $cities);
            $this->assertContains($pair['delivery_city'], $cities);
        }
    }
}
