<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateVehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_update_vehicle_with_valid_data(): void
    {
        $vehicle = Vehicle::factory()->create([
            'manufacturer' => 'Volvo',
            'current_odometer' => 100000,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/vehicles/{$vehicle->id}", [
                'manufacturer' => 'Scania',
                'current_odometer' => 125000,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle updated successfully',
            ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'manufacturer' => 'Scania',
            'current_odometer' => 125000,
        ]);
    }

    public function test_returns_no_changes_message_when_unchanged(): void
    {
        $vehicle = Vehicle::factory()->create(['manufacturer' => 'Volvo']);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/vehicles/{$vehicle->id}", ['manufacturer' => 'Volvo']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No changes detected',
            ]);
    }

    public function test_validation_fails_with_duplicate_plate_number(): void
    {
        Vehicle::factory()->create(['plate_number' => 'FX-DUP-001']);
        $vehicle = Vehicle::factory()->create(['plate_number' => 'FX-OLD-001']);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/vehicles/{$vehicle->id}", ['plate_number' => 'FX-DUP-001']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plate_number']);
    }

    public function test_validation_fails_with_invalid_production_year(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/vehicles/{$vehicle->id}", ['production_year' => 1970]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['production_year']);
    }

    public function test_returns_404_for_non_existent_vehicle(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson('/api/v1/vehicles/550e8400-e29b-41d4-a716-446655440000', [
                'manufacturer' => 'Scania',
            ]);

        $response->assertStatus(404);
    }
}
