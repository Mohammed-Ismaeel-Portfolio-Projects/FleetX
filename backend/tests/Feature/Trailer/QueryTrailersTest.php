<?php

namespace Tests\Feature\Trailer;

use App\Models\Status;
use App\Models\User;
use App\Models\Trailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryTrailersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Status::create(['name' => 'Available', 'type' => 'trailer']);
        Status::create(['name' => 'Maintenance', 'type' => 'trailer']);
    }

    public function test_can_query_trailers(): void
    {
        Trailer::factory()->count(15)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/trailers');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailers retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'data',
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
    }

    public function test_can_filter_trailers_by_type(): void
    {
        Trailer::factory()->create(['trailer_type' => 'Dry Van']);
        Trailer::factory()->create(['trailer_type' => 'Refrigerated Trailer']);
        Trailer::factory()->create(['trailer_type' => 'Flatbed Trailer']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/trailers?trailer_type=Dry Van');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_filter_trailers_by_manufacturer(): void
    {
        Trailer::factory()->create(['manufacturer' => 'Great Dane']);
        Trailer::factory()->create(['manufacturer' => 'Wabash National']);
        Trailer::factory()->create(['manufacturer' => 'Utility Trailer']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/trailers?manufacturer=Great Dane');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_filter_trailers_by_is_active(): void
    {
        Trailer::factory()->create(['is_active' => true]);
        Trailer::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/trailers?is_active=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_search_trailers(): void
    {
        Trailer::factory()->create(['trailer_code' => 'TR-00001', 'fleet_number' => 'FT-1001']);
        Trailer::factory()->create(['trailer_code' => 'TR-00002', 'fleet_number' => 'FT-1002']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/trailers?search=TR-00001');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_sort_trailers(): void
    {
        Trailer::factory()->create(['trailer_code' => 'TR-00003']);
        Trailer::factory()->create(['trailer_code' => 'TR-00001']);
        Trailer::factory()->create(['trailer_code' => 'TR-00002']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/trailers?sort_by=trailer_code&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertEquals('TR-00001', $data[0]['trailer_code']);
    }

    public function test_can_paginate_trailers(): void
    {
        Trailer::factory()->count(25)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/trailers?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data.data'));
        $this->assertEquals(25, $response->json('data.meta.total'));
    }
}
