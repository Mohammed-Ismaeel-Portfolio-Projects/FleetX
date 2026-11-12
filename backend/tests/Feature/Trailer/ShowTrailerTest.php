<?php

namespace Tests\Feature\Trailer;

use App\Models\Status;
use App\Models\User;
use App\Models\Trailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTrailerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Status::create(['name' => 'Available', 'type' => 'trailer']);
    }

    public function test_can_show_trailer(): void
    {
        $trailer = Trailer::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/trailers/{$trailer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer retrieved successfully',
                'data' => [
                    'id' => $trailer->id,
                    'trailer_code' => $trailer->trailer_code,
                    'fleet_number' => $trailer->fleet_number,
                ],
            ]);
    }

    public function test_returns_404_when_trailer_not_found(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/trailers/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }
}
