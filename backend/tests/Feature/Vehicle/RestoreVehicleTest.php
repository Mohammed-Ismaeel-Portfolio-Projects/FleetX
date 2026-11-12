<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreVehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_restore_deleted_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        $vehicle->delete();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/vehicles/{$vehicle->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle restored successfully',
            ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'deleted_at' => null,
        ]);
    }

    public function test_returns_success_when_already_active(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/vehicles/{$vehicle->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle is already active',
            ]);
    }

    public function test_returns_404_for_non_existent_vehicle(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/vehicles/550e8400-e29b-41d4-a716-446655440000/restore');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Vehicle not found',
            ]);
    }
}
