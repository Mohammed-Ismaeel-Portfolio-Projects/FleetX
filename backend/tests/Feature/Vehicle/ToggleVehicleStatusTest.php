<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleVehicleStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_activate_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/vehicles/{$vehicle->id}/status", ['is_active' => true]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle activated successfully',
                'data' => [
                    'id' => $vehicle->id,
                    'is_active' => true,
                ],
            ]);
    }

    public function test_can_deactivate_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/vehicles/{$vehicle->id}/status", ['is_active' => false]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle deactivated successfully',
                'data' => [
                    'id' => $vehicle->id,
                    'is_active' => false,
                ],
            ]);
    }

    public function test_validation_fails_without_is_active_field(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/vehicles/{$vehicle->id}/status", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_returns_404_for_non_existent_vehicle(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/vehicles/550e8400-e29b-41d4-a716-446655440000/status', ['is_active' => true]);

        $response->assertStatus(404);
    }
}
