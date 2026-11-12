<?php

namespace Tests\Feature\Vehicle;

use App\Models\FuelType;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateVehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private VehicleType $vehicleType;
    private FuelType $fuelType;
    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->vehicleType = VehicleType::create(['name' => 'Tractor Unit', 'code' => 'TRACTOR_UNIT', 'is_active' => true]);
        $this->fuelType = FuelType::create(['name' => 'Diesel', 'code' => 'DIESEL', 'is_active' => true]);
        $this->status = Status::create(['name' => 'Available', 'type' => 'vehicle']);
    }

    public function test_can_create_vehicle_with_valid_data(): void
    {
        $data = $this->validPayload();

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle created successfully',
            ]);

        $this->assertDatabaseHas('vehicles', [
            'operational_code' => 'VH-00001',
            'fleet_number' => 'FX-1001',
            'plate_number' => 'FX-1001-US',
            'manufacturer' => 'Volvo',
        ]);
    }

    public function test_validation_fails_with_missing_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/vehicles', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'operational_code',
                'fleet_number',
                'plate_number',
                'manufacturer',
                'model',
                'vehicle_type_id',
                'fuel_type_id',
                'status_id',
            ]);
    }

    public function test_validation_fails_with_duplicate_plate_number(): void
    {
        Vehicle::factory()->create(['plate_number' => 'FX-1001-US']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/vehicles', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plate_number']);
    }

    public function test_validation_fails_with_duplicate_fleet_number(): void
    {
        Vehicle::factory()->create(['fleet_number' => 'FX-1001']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/vehicles', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_number']);
    }

    public function test_validation_fails_when_payload_exceeds_gross_weight(): void
    {
        $data = $this->validPayload([
            'payload_capacity_kg' => 44000,
            'gross_weight_kg' => 18000,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/vehicles', $data);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to create vehicle: Payload capacity cannot exceed gross vehicle weight.',
            ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'operational_code' => 'VH-00001',
            'fleet_number' => 'FX-1001',
            'plate_number' => 'FX-1001-US',
            'vin' => '1FVFLEETX00000001',
            'manufacturer' => 'Volvo',
            'model' => 'FH16',
            'production_year' => 2022,
            'vehicle_type_id' => $this->vehicleType->id,
            'fuel_type_id' => $this->fuelType->id,
            'transmission' => 'automatic',
            'axle_count' => 5,
            'payload_capacity_kg' => 24000,
            'gross_weight_kg' => 40000,
            'fuel_tank_capacity_l' => 900,
            'current_odometer' => 125000,
            'status_id' => $this->status->id,
            'is_active' => true,
            'gps_device_identifier' => 'GPS-FX-1001',
            'insurance_expiry_date' => now()->addMonths(10)->toDateString(),
            'registration_expiry_date' => now()->addYear()->toDateString(),
            'inspection_expiry_date' => now()->addMonths(6)->toDateString(),
            'notes' => 'Long-haul tractor unit for international freight.',
        ], $overrides);
    }
}
