<?php

namespace Tests\Feature\Assignment;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDispatcherUser;
use Tests\TestCase;

class DeleteAssignmentTest extends TestCase
{
    use CreatesDispatcherUser;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeDispatcherUser();
    }

    public function test_can_soft_delete_assignment(): void
    {
        $assignment = Assignment::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/assignments/{$assignment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment deleted successfully',
            ]);

        $this->assertSoftDeleted('assignments', ['id' => $assignment->id]);
    }

    public function test_returns_404_for_non_existent_assignment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->deleteJson('/api/v1/assignments/550e8400-e29b-41d4-a716-446655440000');

        $response->assertStatus(404);
    }

    public function test_soft_delete_sets_updated_by(): void
    {
        $assignment = Assignment::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/assignments/{$assignment->id}");

        $response->assertStatus(200);
        $assignment->refresh();
        $this->assertEquals($this->user->id, $assignment->updated_by);
    }

    public function test_deleted_assignment_not_included_in_query(): void
    {
        $assignment = Assignment::factory()->create();
        Assignment::factory()->count(3)->create();

        // Delete one assignment
        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/assignments/{$assignment->id}");

        // Query assignments
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/assignments');

        $response->assertStatus(200);
        $assignmentIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($assignment->id, $assignmentIds);
    }

    public function test_can_delete_active_assignment(): void
    {
        $assignment = Assignment::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/assignments/{$assignment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('assignments', ['id' => $assignment->id]);
    }
}
