<?php

namespace Tests\Feature\Assignment;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDispatcherUser;
use Tests\TestCase;

class RestoreAssignmentTest extends TestCase
{
    use CreatesDispatcherUser;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeDispatcherUser();
    }

    public function test_can_restore_deleted_assignment(): void
    {
        $assignment = Assignment::factory()->create();
        $assignment->delete();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$assignment->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment restored successfully',
            ]);

        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'deleted_at' => null,
        ]);
    }

    public function test_returns_success_when_already_active(): void
    {
        $assignment = Assignment::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$assignment->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment already restored or no changes needed',
            ]);
    }

    public function test_returns_404_for_non_existent_assignment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/assignments/550e8400-e29b-41d4-a716-446655440000/restore');

        $response->assertStatus(404);
    }

    public function test_restored_assignment_appears_in_query(): void
    {
        $assignment = Assignment::factory()->create();
        $assignment->delete();

        // Before restore, should not appear
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments');
        $assignmentIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($assignment->id, $assignmentIds);

        // Restore
        $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$assignment->id}/restore");

        // After restore, should appear
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments');
        $assignmentIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($assignment->id, $assignmentIds);
    }

    public function test_restore_sets_updated_by(): void
    {
        $assignment = Assignment::factory()->create();
        $assignment->delete();

        $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$assignment->id}/restore");

        $assignment->refresh();
        $this->assertEquals($this->user->id, $assignment->updated_by);
    }

    public function test_response_includes_shipment_object(): void
    {
        $assignment = Assignment::factory()->create();
        $assignment->delete();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$assignment->id}/restore");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'shipment_id',
                    'shipment' => [
                        'id',
                        'shipment_number',
                    ],
                ],
            ]);
    }
}
