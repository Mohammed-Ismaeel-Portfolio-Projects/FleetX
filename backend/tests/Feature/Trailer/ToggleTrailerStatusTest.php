<?php

namespace Tests\Feature\Trailer;

use App\Models\Status;
use App\Models\User;
use App\Models\Trailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleTrailerStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Status::create(['name' => 'Available', 'type' => 'trailer']);
    }

    public function test_can_activate_trailer(): void
    {
        $trailer = Trailer::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/trailers/{$trailer->id}/status", [
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer activated successfully',
                'data' => [
                    'id' => $trailer->id,
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('trailers', [
            'id' => $trailer->id,
            'is_active' => true,
        ]);
    }

    public function test_can_deactivate_trailer(): void
    {
        $trailer = Trailer::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/trailers/{$trailer->id}/status", [
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer deactivated successfully',
                'data' => [
                    'id' => $trailer->id,
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('trailers', [
            'id' => $trailer->id,
            'is_active' => false,
        ]);
    }

    public function test_returns_no_change_when_status_already_set(): void
    {
        $trailer = Trailer::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/trailers/{$trailer->id}/status", [
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer already has the requested status',
                'data' => [
                    'id' => $trailer->id,
                    'is_active' => true,
                ],
            ]);
    }

    public function test_validation_fails_without_is_active(): void
    {
        $trailer = Trailer::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/trailers/{$trailer->id}/status", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }
}
