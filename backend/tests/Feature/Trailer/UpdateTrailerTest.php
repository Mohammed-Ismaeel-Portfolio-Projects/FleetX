<?php

namespace Tests\Feature\Trailer;

use App\Models\Status;
use App\Models\User;
use App\Models\Trailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTrailerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->status = Status::create(['name' => 'Available', 'type' => 'trailer']);
    }

    public function test_can_update_trailer(): void
    {
        $trailer = Trailer::factory()->create(['manufacturer' => 'Great Dane']);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/trailers/{$trailer->id}", [
                'manufacturer' => 'Wabash National',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer updated successfully',
                'data' => [
                    'manufacturer' => 'Wabash National',
                ],
            ]);

        $this->assertDatabaseHas('trailers', [
            'id' => $trailer->id,
            'manufacturer' => 'Wabash National',
        ]);
    }

    public function test_validation_fails_with_duplicate_trailer_code(): void
    {
        $trailer1 = Trailer::factory()->create(['trailer_code' => 'TR-00001']);
        $trailer2 = Trailer::factory()->create(['trailer_code' => 'TR-00002']);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/trailers/{$trailer2->id}", [
                'trailer_code' => 'TR-00001',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trailer_code']);
    }

    public function test_returns_no_changes_when_same_data(): void
    {
        $trailer = Trailer::factory()->create(['manufacturer' => 'Great Dane']);

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/trailers/{$trailer->id}", [
                'manufacturer' => 'Great Dane',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No changes detected',
            ]);
    }
}
