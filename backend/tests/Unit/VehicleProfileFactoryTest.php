<?php

namespace Tests\Unit;

use App\Models\Assignment;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Simulation\Routing\Application\Factories\VehicleProfileFactory;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleProfileFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_truck_profile(): void
    {
        $profile = VehicleProfileFactory::defaultTruck();

        $this->assertSame(VehicleProfile::FREIGHT_HEAVY_TRUCK, $profile->freightClass);
        $this->assertSame(4.0, $profile->height);
        $this->assertSame(2.55, $profile->width);
        $this->assertSame(16.5, $profile->length);
        $this->assertSame(40000.0, $profile->weight);
        $this->assertTrue($profile->hasTrailer);
    }

    public function test_from_vehicle_maps_heavy_truck_dimensions(): void
    {
        $vehicle = Vehicle::factory()->create([
            'routing_class' => Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
            'gross_weight_kg' => 40000,
            'height_m' => 4.0,
            'width_m' => 2.55,
            'length_m' => 16.5,
        ]);

        $profile = VehicleProfileFactory::fromVehicle($vehicle, true);

        $this->assertSame(VehicleProfile::FREIGHT_HEAVY_TRUCK, $profile->freightClass);
        $this->assertSame(4.0, $profile->height);
        $this->assertSame(2.55, $profile->width);
        $this->assertSame(16.5, $profile->length);
        $this->assertSame(40000.0, $profile->weight);
        $this->assertTrue($profile->hasTrailer);
        $this->assertSame('heavy_truck', $profile->toArray()['type']);
    }

    public function test_from_assignment_uses_assigned_truck(): void
    {
        $vehicle = Vehicle::factory()->create([
            'routing_class' => Vehicle::ROUTING_CLASS_LIGHT_TRUCK,
            'gross_weight_kg' => 7500,
            'height_m' => 3.1,
            'width_m' => 2.40,
            'length_m' => 8.5,
        ]);

        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => null,
            'is_active' => true,
        ]);
        $assignment->setRelation('vehicle', $vehicle);
        $assignment->setRelation('trailer', null);

        $profile = VehicleProfileFactory::fromAssignment($assignment);

        $this->assertSame(VehicleProfile::FREIGHT_LIGHT_TRUCK, $profile->freightClass);
        $this->assertSame(3.1, $profile->height);
        $this->assertSame(2.40, $profile->width);
        $this->assertSame(8.5, $profile->length);
        $this->assertSame(7500.0, $profile->weight);
        $this->assertFalse($profile->hasTrailer);
    }

    public function test_from_assignment_enlarges_envelope_with_trailer(): void
    {
        $vehicle = Vehicle::factory()->create([
            'routing_class' => Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
            'gross_weight_kg' => 40000,
            'height_m' => 3.5,
            'width_m' => 2.50,
            'length_m' => 16.5,
        ]);

        $trailer = Trailer::factory()->create([
            'height_m' => 4.0,
            'width_m' => 2.55,
            'length_m' => 13.6,
        ]);

        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'is_active' => true,
        ]);
        $assignment->setRelation('vehicle', $vehicle);
        $assignment->setRelation('trailer', $trailer);

        $profile = VehicleProfileFactory::fromAssignment($assignment);

        $this->assertTrue($profile->hasTrailer);
        $this->assertSame(4.0, $profile->height);
        $this->assertSame(2.55, $profile->width);
        $this->assertSame(16.5, $profile->length);
    }

    public function test_null_assignment_falls_back_to_default_truck(): void
    {
        $profile = VehicleProfileFactory::fromAssignment(null);

        $this->assertSame(VehicleProfile::FREIGHT_HEAVY_TRUCK, $profile->freightClass);
        $this->assertSame(40000.0, $profile->weight);
    }
}
