<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryDriversTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    public function test_can_query_drivers(): void
    {
        Driver::factory()->count(15)->create();

        $response = $this->getJson('/api/v1/drivers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'employee_number',
                        'first_name',
                        'last_name',
                        'email',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_can_filter_drivers_by_nationality(): void
    {
        Driver::factory()->create(['nationality' => 'American']);
        Driver::factory()->create(['nationality' => 'British']);
        Driver::factory()->create(['nationality' => 'American']);

        $response = $this->getJson('/api/v1/drivers?nationality=American');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_can_filter_drivers_by_license_class(): void
    {
        Driver::factory()->create(['license_class' => 'CE']);
        Driver::factory()->create(['license_class' => 'C']);
        Driver::factory()->create(['license_class' => 'CE']);

        $response = $this->getJson('/api/v1/drivers?license_class=CE');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_can_filter_drivers_by_status(): void
    {
        $status1 = Status::factory()->create(['type' => 'driver']);
        $status2 = Status::factory()->create(['type' => 'driver']);

        Driver::factory()->count(3)->create(['status_id' => $status1->id]);
        Driver::factory()->count(2)->create(['status_id' => $status2->id]);

        $response = $this->getJson("/api/v1/drivers?status_id={$status1->id}");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_can_filter_drivers_by_is_active(): void
    {
        Driver::factory()->count(5)->create(['is_active' => true]);
        Driver::factory()->count(3)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/drivers?is_active=true');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_can_filter_drivers_by_experience_range(): void
    {
        Driver::factory()->create(['years_of_experience' => 2]);
        Driver::factory()->create(['years_of_experience' => 5]);
        Driver::factory()->create(['years_of_experience' => 10]);
        Driver::factory()->create(['years_of_experience' => 15]);

        $response = $this->getJson('/api/v1/drivers?min_experience=5&max_experience=10');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_can_search_drivers(): void
    {
        Driver::factory()->create([
            'employee_number' => 'EMP-10001',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john.smith@example.com',
            'license_number' => 'DL-12345678',
        ]);

        Driver::factory()->create([
            'employee_number' => 'EMP-10002',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'license_number' => 'DL-87654321',
        ]);

        $response = $this->getJson('/api/v1/drivers?search=John');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_can_sort_drivers(): void
    {
        Driver::factory()->create(['employee_number' => 'EMP-10003', 'first_name' => 'Charlie']);
        Driver::factory()->create(['employee_number' => 'EMP-10001', 'first_name' => 'Alice']);
        Driver::factory()->create(['employee_number' => 'EMP-10002', 'first_name' => 'Bob']);

        $response = $this->getJson('/api/v1/drivers?sort_by=first_name&sort_direction=asc');

        $response->assertStatus(200);
        $drivers = $response->json('data');
        $this->assertEquals('Alice', $drivers[0]['first_name']);
        $this->assertEquals('Bob', $drivers[1]['first_name']);
        $this->assertEquals('Charlie', $drivers[2]['first_name']);
    }

    public function test_can_paginate_drivers(): void
    {
        Driver::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/drivers?per_page=10&page=2');

        $response->assertStatus(200);
        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(2, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
    }
}
