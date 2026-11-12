<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleDriverStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    public function test_can_activate_driver(): void
    {
        $driver = Driver::factory()->create(['is_active' => false]);

        $response = $this->patchJson("/api/v1/drivers/{$driver->id}/status", [
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $driver->id,
                    'is_active' => true,
                ],
                'message' => 'Driver activated successfully',
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'is_active' => true,
        ]);
    }

    public function test_can_deactivate_driver(): void
    {
        $driver = Driver::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/api/v1/drivers/{$driver->id}/status", [
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $driver->id,
                    'is_active' => false,
                ],
                'message' => 'Driver deactivated successfully',
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'is_active' => false,
        ]);
    }

    public function test_returns_no_change_when_status_already_set(): void
    {
        $driver = Driver::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/api/v1/drivers/{$driver->id}/status", [
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $driver->id,
                    'is_active' => true,
                ],
                'message' => 'Driver already has the requested status',
            ]);
    }

    public function test_validation_fails_without_is_active(): void
    {
        $driver = Driver::factory()->create();

        $response = $this->patchJson("/api/v1/drivers/{$driver->id}/status", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }
}
