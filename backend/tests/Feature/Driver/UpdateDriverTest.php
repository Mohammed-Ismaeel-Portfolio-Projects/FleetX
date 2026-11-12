<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    public function test_can_update_driver(): void
    {
        $driver = Driver::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->putJson("/api/v1/drivers/{$driver->id}", [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $driver->id,
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                ],
            ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }

    public function test_cannot_update_driver_with_duplicate_employee_number(): void
    {
        $driver1 = Driver::factory()->create(['employee_number' => 'EMP-10001']);
        $driver2 = Driver::factory()->create(['employee_number' => 'EMP-10002']);

        $response = $this->putJson("/api/v1/drivers/{$driver2->id}", [
            'employee_number' => 'EMP-10001',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_number']);
    }

    public function test_cannot_update_driver_with_duplicate_email(): void
    {
        $driver1 = Driver::factory()->create(['email' => 'driver1@example.com']);
        $driver2 = Driver::factory()->create(['email' => 'driver2@example.com']);

        $response = $this->putJson("/api/v1/drivers/{$driver2->id}", [
            'email' => 'driver1@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_returns_no_changes_detected_when_no_fields_changed(): void
    {
        $driver = Driver::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->putJson("/api/v1/drivers/{$driver->id}", [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'No changes detected',
            ]);
    }
}
