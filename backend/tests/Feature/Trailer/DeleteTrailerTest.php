<?php

namespace Tests\Feature\Trailer;

use App\Models\Status;
use App\Models\User;
use App\Models\Trailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTrailerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Status::create(['name' => 'Available', 'type' => 'trailer']);
    }

    public function test_can_delete_trailer(): void
    {
        $trailer = Trailer::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/trailers/{$trailer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer deleted successfully',
            ]);

        $this->assertSoftDeleted('trailers', [
            'id' => $trailer->id,
        ]);
    }

    public function test_returns_already_deleted_when_trailer_already_soft_deleted(): void
    {
        $trailer = Trailer::factory()->create();
        $trailer->delete();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/trailers/{$trailer->id}");

        $response->assertStatus(404);
    }
}
