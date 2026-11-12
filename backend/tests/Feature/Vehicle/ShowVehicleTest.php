<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowVehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_show_existing_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create([
            'operational_code' => 'VH-90001',
            'fleet_number' => 'FX-9001',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/vehicles/{$vehicle->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Vehicle retrieved successfully',
                'data' => [
                    'operational_code' => 'VH-90001',
                    'fleet_number' => 'FX-9001',
                ],
            ]);
    }

    public function test_returns_404_for_missing_vehicle(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/vehicles/550e8400-e29b-41d4-a716-446655440000');

        $response->assertStatus(404);
    }

    public function test_does_not_expose_internal_fields(): void
    {
        $vehicle = Vehicle::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/vehicles/{$vehicle->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayNotHasKey('deleted_at', $data);
        $this->assertArrayNotHasKey('created_by', $data);
        $this->assertArrayNotHasKey('updated_by', $data);
    }
}
