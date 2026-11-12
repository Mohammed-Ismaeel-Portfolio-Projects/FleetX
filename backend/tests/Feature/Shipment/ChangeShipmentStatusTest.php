<?php

namespace Tests\Feature\Shipment;

use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentStatus;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeShipmentStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shipment $shipment;
    private Status $assignedStatus;
    private Status $draftStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ShipmentStatusesSeeder::class);

        $this->user = User::factory()->create();
        $this->draftStatus = Status::where('name', ShipmentStatus::DRAFT)->where('type', ShipmentStatus::TYPE)->firstOrFail();
        $this->assignedStatus = Status::where('name', ShipmentStatus::ASSIGNED)->where('type', ShipmentStatus::TYPE)->firstOrFail();
        $waitingStatus = Status::where('name', ShipmentStatus::WAITING_ASSIGNMENT)->where('type', ShipmentStatus::TYPE)->firstOrFail();
        $this->shipment = Shipment::factory()->create(['status_id' => $waitingStatus->id]);
    }

    public function test_can_change_shipment_status(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/shipments/{$this->shipment->id}/status", [
                'status_id' => $this->assignedStatus->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shipment status changed successfully',
            ]);

        $this->assertDatabaseHas('shipments', [
            'id' => $this->shipment->id,
            'status_id' => $this->assignedStatus->id,
        ]);
    }

    public function test_unsupported_transition_is_rejected(): void
    {
        $draftShipment = Shipment::factory()->create(['status_id' => $this->draftStatus->id]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/shipments/{$draftShipment->id}/status", [
                'status_id' => $this->assignedStatus->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('shipments', [
            'id' => $draftShipment->id,
            'status_id' => $this->draftStatus->id,
        ]);
    }

    public function test_validation_fails_with_missing_status_id(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/shipments/{$this->shipment->id}/status", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);
    }

    public function test_validation_fails_with_non_existent_status(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/shipments/{$this->shipment->id}/status", [
                'status_id' => 999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);
    }

    public function test_validation_rejects_legacy_snake_case_shipment_status_row(): void
    {
        $legacy = Status::query()->create([
            'name' => 'in_transit',
            'type' => ShipmentStatus::TYPE,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/shipments/{$this->shipment->id}/status", [
                'status_id' => $legacy->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);

        $this->assertDatabaseHas('shipments', [
            'id' => $this->shipment->id,
            'status_id' => Status::where('name', ShipmentStatus::WAITING_ASSIGNMENT)
                ->where('type', ShipmentStatus::TYPE)
                ->value('id'),
        ]);
    }

    public function test_validation_rejects_non_shipment_status_row(): void
    {
        $assignmentStatus = Status::query()->create([
            'name' => 'Active',
            'type' => 'assignment',
        ]);

        $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/shipments/{$this->shipment->id}/status", [
                'status_id' => $assignmentStatus->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);
    }

    public function test_returns_404_for_non_existent_shipment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/shipments/550e8400-e29b-41d4-a716-446655440000/status', [
                'status_id' => $this->assignedStatus->id,
            ]);

        $response->assertStatus(404);
    }
}
