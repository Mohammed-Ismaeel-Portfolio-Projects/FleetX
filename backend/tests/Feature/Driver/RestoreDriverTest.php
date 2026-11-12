<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    public function test_can_restore_deleted_driver(): void
    {
        $driver = Driver::factory()->create();
        $driver->delete();

        $response = $this->patchJson("/api/v1/drivers/{$driver->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Driver restored successfully',
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'deleted_at' => null,
        ]);
    }

    public function test_returns_already_active_when_driver_not_deleted(): void
    {
        $driver = Driver::factory()->create();

        $response = $this->patchJson("/api/v1/drivers/{$driver->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Driver is already active',
            ]);
    }

    public function test_returns_404_for_nonexistent_driver(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->patchJson("/api/v1/drivers/{$fakeId}/restore");

        $response->assertStatus(404);
    }
}
