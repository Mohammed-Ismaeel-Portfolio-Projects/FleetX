<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    public function test_can_show_driver(): void
    {
        $driver = Driver::factory()->create();

        $response = $this->getJson("/api/v1/drivers/{$driver->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'employee_number',
                    'first_name',
                    'last_name',
                    'full_name',
                    'email',
                    'phone',
                    'nationality',
                    'date_of_birth',
                    'employment_date',
                    'license_number',
                    'license_class',
                    'license_expiry_date',
                    'years_of_experience',
                    'status_id',
                    'is_active',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $driver->id,
                    'employee_number' => $driver->employee_number,
                    'first_name' => $driver->first_name,
                    'last_name' => $driver->last_name,
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_driver(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->getJson("/api/v1/drivers/{$fakeId}");

        $response->assertStatus(404);
    }
}
