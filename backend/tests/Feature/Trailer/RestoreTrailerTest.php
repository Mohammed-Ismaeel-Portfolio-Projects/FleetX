<?php

namespace Tests\Feature\Trailer;

use App\Models\Status;
use App\Models\User;
use App\Models\Trailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreTrailerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Status::create(['name' => 'Available', 'type' => 'trailer']);
    }

    public function test_can_restore_trailer(): void
    {
        $trailer = Trailer::factory()->create();
        $trailer->delete();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/trailers/{$trailer->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer restored successfully',
            ]);

        $this->assertDatabaseHas('trailers', [
            'id' => $trailer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_returns_already_active_when_trailer_not_deleted(): void
    {
        $trailer = Trailer::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/trailers/{$trailer->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer is already active',
            ]);
    }

    public function test_returns_404_when_trailer_not_found(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/trailers/00000000-0000-0000-0000-000000000000/restore');

        $response->assertStatus(404);
    }
}
