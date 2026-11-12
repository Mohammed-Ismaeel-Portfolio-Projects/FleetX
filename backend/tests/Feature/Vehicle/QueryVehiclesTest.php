<?php

namespace Tests\Feature\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryVehiclesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_query_vehicles_with_pagination(): void
    {
        Vehicle::factory()->count(25)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/vehicles?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'operational_code',
                            'fleet_number',
                            'plate_number',
                            'manufacturer',
                            'model',
                            'is_active',
                        ],
                    ],
                    'meta' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'from',
                        'to',
                    ],
                    'links' => [
                        'first',
                        'last',
                        'prev',
                        'next',
                    ],
                ],
            ]);

        $this->assertEquals(10, count($response->json('data.data')));
        $this->assertEquals(25, $response->json('data.meta.total'));
    }

    public function test_can_search_vehicles_by_plate_number(): void
    {
        Vehicle::factory()->create(['plate_number' => 'FX-SEARCH-001']);
        Vehicle::factory()->create(['plate_number' => 'FX-OTHER-001']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/vehicles?search=SEARCH');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data.data')));
        $this->assertEquals('FX-SEARCH-001', $response->json('data.data.0.plate_number'));
    }

    public function test_can_filter_vehicles_by_status(): void
    {
        Vehicle::factory()->count(5)->create(['is_active' => true]);
        Vehicle::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/vehicles?is_active=true');

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data.data')));
    }

    public function test_can_sort_vehicles_by_fleet_number(): void
    {
        Vehicle::factory()->create(['fleet_number' => 'FX-3000']);
        Vehicle::factory()->create(['fleet_number' => 'FX-1000']);
        Vehicle::factory()->create(['fleet_number' => 'FX-2000']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/vehicles?sort_by=fleet_number&sort_direction=asc');

        $response->assertStatus(200);
        $vehicles = $response->json('data.data');
        $this->assertEquals('FX-1000', $vehicles[0]['fleet_number']);
        $this->assertEquals('FX-2000', $vehicles[1]['fleet_number']);
        $this->assertEquals('FX-3000', $vehicles[2]['fleet_number']);
    }
}
