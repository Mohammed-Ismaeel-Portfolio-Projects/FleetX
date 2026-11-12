<?php

namespace Tests\Feature\Assignment;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDispatcherUser;
use Tests\TestCase;

class QueryAssignmentsTest extends TestCase
{
    use CreatesDispatcherUser;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeDispatcherUser();
    }

    public function test_can_query_all_assignments(): void
    {
        Assignment::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_query_returns_paginated_results(): void
    {
        Assignment::factory()->count(20)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_can_filter_assignments_by_driver(): void
    {
        $driver = Driver::factory()->create();
        Assignment::factory()->create(['driver_id' => $driver->id]);
        Assignment::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/assignments?driver_id={$driver->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($driver->id, $response->json('data.0.driver_id'));
    }

    public function test_can_filter_assignments_by_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();
        Assignment::factory()->create(['vehicle_id' => $vehicle->id]);
        Assignment::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/assignments?vehicle_id={$vehicle->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($vehicle->id, $response->json('data.0.vehicle_id'));
    }

    public function test_can_filter_assignments_by_trailer(): void
    {
        $trailer = Trailer::factory()->create();
        Assignment::factory()->create(['trailer_id' => $trailer->id]);
        Assignment::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/assignments?trailer_id={$trailer->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($trailer->id, $response->json('data.0.trailer_id'));
    }

    public function test_can_filter_assignments_by_shipment(): void
    {
        $shipment = Shipment::factory()->create();
        Assignment::factory()->create(['shipment_id' => $shipment->id]);
        Assignment::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/assignments?shipment_id={$shipment->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($shipment->id, $response->json('data.0.shipment_id'));
    }

    public function test_can_filter_assignments_by_status(): void
    {
        $assignment = Assignment::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/assignments?status_id={$assignment->status_id}");

        $response->assertStatus(200)
            ->assertJsonCount(true, 'data');

        foreach ($response->json('data') as $item) {
            $this->assertEquals($assignment->status_id, $item['status_id']);
        }
    }

    public function test_can_filter_assignments_by_active_status(): void
    {
        Assignment::factory()->create(['is_active' => true]);
        Assignment::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments?is_active=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertTrue($response->json('data.0.is_active'));
    }

    public function test_can_filter_assignments_by_inactive_status(): void
    {
        Assignment::factory()->create(['is_active' => true]);
        Assignment::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments?is_active=0');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $item) {
            $this->assertFalse($item['is_active']);
        }
    }

    public function test_can_search_assignments_by_assignment_code(): void
    {
        Assignment::factory()->create(['assignment_code' => 'ASS-00001']);
        Assignment::factory()->create(['assignment_code' => 'ASS-00002']);
        Assignment::factory()->create(['assignment_code' => 'ASS-00003']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments?search=ASS-00001');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_can_search_assignments_by_driver_name(): void
    {
        $driver = Driver::factory()->create(['full_name' => 'John Doe']);
        Assignment::factory()->create(['driver_id' => $driver->id]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments?search=John');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_search_assignments_by_shipment_number(): void
    {
        $shipment = Shipment::factory()->create(['shipment_number' => 'SHIP-00001']);
        Assignment::factory()->create(['shipment_id' => $shipment->id]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments?search=SHIP-00001');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_combine_multiple_filters(): void
    {
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $shipment = Shipment::factory()->create();

        Assignment::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'shipment_id' => $shipment->id,
            'is_active' => true,
        ]);

        Assignment::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/assignments?driver_id={$driver->id}&vehicle_id={$vehicle->id}&shipment_id={$shipment->id}&is_active=1");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_sort_assignments_by_created_date(): void
    {
        $assignment1 = Assignment::factory()->create(['created_at' => now()->subDays(2)]);
        $assignment2 = Assignment::factory()->create(['created_at' => now()->subDay()]);
        $assignment3 = Assignment::factory()->create(['created_at' => now()]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments?sort_by=created_at&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals($assignment1->id, $data[0]['id']);
        $this->assertEquals($assignment2->id, $data[1]['id']);
        $this->assertEquals($assignment3->id, $data[2]['id']);
    }

    public function test_response_includes_shipment_objects(): void
    {
        Assignment::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'shipment_id',
                        'shipment' => [
                            'id',
                            'shipment_number',
                        ],
                    ],
                ],
            ]);
    }

    public function test_does_not_return_deleted_assignments(): void
    {
        $activeAssignment = Assignment::factory()->create();
        $deletedAssignment = Assignment::factory()->create();
        $deletedAssignment->delete();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($activeAssignment->id, $response->json('data.0.id'));
    }
}
