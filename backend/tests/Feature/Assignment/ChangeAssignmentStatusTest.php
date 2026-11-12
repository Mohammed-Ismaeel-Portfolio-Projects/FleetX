<?php

namespace Tests\Feature\Assignment;

use App\Models\Assignment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDispatcherUser;
use Tests\TestCase;

class ChangeAssignmentStatusTest extends TestCase
{
    use CreatesDispatcherUser;
    use RefreshDatabase;

    private User $user;
    private Assignment $assignment;
    private Status $newStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeDispatcherUser();
        $this->assignment = Assignment::factory()->create();
        $this->newStatus = Status::create(['name' => 'In Operation', 'type' => 'assignment']);
    }

    public function test_can_change_assignment_status(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/status", [
                'status_id' => $this->newStatus->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment status changed successfully',
            ]);

        $this->assertDatabaseHas('assignments', [
            'id' => $this->assignment->id,
            'status_id' => $this->newStatus->id,
        ]);
    }

    public function test_returns_no_changes_when_status_unchanged(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/status", [
                'status_id' => $this->assignment->status_id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No changes detected',
            ]);
    }

    public function test_validation_fails_with_missing_status_id(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/status", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);
    }

    public function test_validation_fails_with_non_existent_status(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/status", [
                'status_id' => 999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);
    }

    public function test_returns_404_for_non_existent_assignment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/assignments/550e8400-e29b-41d4-a716-446655440000/status', [
                'status_id' => $this->newStatus->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_response_includes_shipment_object(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/status", [
                'status_id' => $this->newStatus->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'shipment_id',
                    'status_id',
                    'shipment' => [
                        'id',
                        'shipment_number',
                    ],
                ],
            ]);
    }

    public function test_can_transition_through_multiple_statuses(): void
    {
        $statusDraft = Status::create(['name' => 'Draft', 'type' => 'assignment']);
        $statusAssigned = Status::create(['name' => 'Assigned', 'type' => 'assignment']);
        $statusReady = Status::create(['name' => 'Ready', 'type' => 'assignment']);

        $this->assignment->update(['status_id' => $statusDraft->id]);

        // Draft -> Assigned
        $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/status", [
                'status_id' => $statusAssigned->id,
            ])->assertStatus(200);

        // Assigned -> Ready
        $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/assignments/{$this->assignment->id}/status", [
                'status_id' => $statusReady->id,
            ])->assertStatus(200);

        $this->assertDatabaseHas('assignments', [
            'id' => $this->assignment->id,
            'status_id' => $statusReady->id,
        ]);
    }
}
