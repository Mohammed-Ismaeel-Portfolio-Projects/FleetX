<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    public function test_can_delete_driver(): void
    {
        $driver = Driver::factory()->create();

        $response = $this->deleteJson("/api/v1/drivers/{$driver->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Driver deleted successfully',
            ]);

        $this->assertSoftDeleted('drivers', [
            'id' => $driver->id,
        ]);
    }

    public function test_returns_already_deleted_when_driver_already_deleted(): void
    {
        $driver = Driver::factory()->create();
        $driver->delete();

        $response = $this->deleteJson("/api/v1/drivers/{$driver->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Driver already deleted',
            ]);
    }
}
