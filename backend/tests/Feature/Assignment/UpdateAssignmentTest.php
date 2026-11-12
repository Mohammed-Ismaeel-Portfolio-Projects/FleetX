<?php

namespace Tests\Feature\Assignment;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDispatcherUser;
use Tests\TestCase;

class UpdateAssignmentTest extends TestCase
{
    use CreatesDispatcherUser;
    use RefreshDatabase;

    private User $user;
    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeDispatcherUser();
        $this->assignment = Assignment::factory()->create();
    }

    public function test_can_update_assignment_with_valid_data(): void
    {
        $newDriver = Driver::factory()->create();
        $newVehicle = Vehicle::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/assignments/{$this->assignment->id}", [
                'driver_id' => $newDriver->id,
                'vehicle_id' => $newVehicle->id,
                'notes' => 'Updated notes',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment updated successfully',
            ]);

        $this->assertDatabaseHas('assignments', [
            'id' => $this->assignment->id,
            'driver_id' => $newDriver->id,
            'vehicle_id' => $newVehicle->id,
            'notes' => 'Updated notes',
        ]);
    }

    public function test_returns_no_changes_message_when_unchanged(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/assignments/{$this->assignment->id}", [
                'driver_id' => $this->assignment->driver_id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No changes detected',
            ]);
    }

    public function test_can_update_assignment_without_changing_shipment(): void
    {
        $originalShipmentId = $this->assignment->shipment_id;

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/assignments/{$this->assignment->id}", [
                'notes' => 'Updated notes only',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('assignments', [
            'id' => $this->assignment->id,
            'shipment_id' => $originalShipmentId,
        ]);
    }

    public function test_validation_fails_with_non_existent_driver(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/assignments/{$this->assignment->id}", [
                'driver_id' => '550e8400-e29b-41d4-a716-446655440000',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['driver_id']);
    }

    public function test_validation_fails_with_non_existent_vehicle(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/assignments/{$this->assignment->id}", [
                'vehicle_id' => '550e8400-e29b-41d4-a716-446655440000',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_validation_fails_with_non_existent_trailer(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/assignments/{$this->assignment->id}", [
                'trailer_id' => '550e8400-e29b-41d4-a716-446655440000',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trailer_id']);
    }

    public function test_returns_404_for_non_existent_assignment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson('/api/v1/assignments/550e8400-e29b-41d4-a716-446655440000', [
                'notes' => 'Test',
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_change_shipment_if_multiple_active_assignments_exist(): void
    {
        // Create assignment active for current shipment
        $this->assignment->update(['is_active' => true]);

        // Create another shipment with existing active assignment
        $otherShipment = Shipment::factory()->create();
        Assignment::factory()->create([
            'shipment_id' => $otherShipment->id,
            'is_active' => true,
        ]);

        // Attempt to move active assignment to other shipment
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/assignments/{$this->assignment->id}", [
                'shipment_id' => $otherShipment->id,
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to update assignment: Target shipment already has an active assignment.',
            ]);
    }

    public function test_response_includes_shipment_object(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/assignments/{$this->assignment->id}", [
                'notes' => 'Updated',
            ]);

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
