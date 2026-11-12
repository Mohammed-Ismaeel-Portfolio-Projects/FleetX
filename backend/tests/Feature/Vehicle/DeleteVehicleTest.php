<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteVehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_soft_delete_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/vehicles/{$vehicle->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle deleted successfully',
            ]);

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_returns_404_for_non_existent_vehicle(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->deleteJson('/api/v1/vehicles/550e8400-e29b-41d4-a716-446655440000');

        $response->assertStatus(404);
    }

    public function test_soft_delete_sets_updated_by(): void
    {
        $vehicle = Vehicle::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/vehicles/{$vehicle->id}");

        $response->assertStatus(200);
        $vehicle->refresh();
        $this->assertEquals($this->user->id, $vehicle->updated_by);
    }
}
