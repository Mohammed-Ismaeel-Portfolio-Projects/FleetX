<?php

namespace Tests\Feature\Shipment;

use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDispatcherUser;
use Tests\TestCase;

class UpdateShipmentTest extends TestCase
{
    use CreatesDispatcherUser;
    use RefreshDatabase;

    private User $user;
    private Shipment $shipment;
    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeDispatcherUser();
        $this->status = Status::create(['name' => 'Draft', 'type' => 'shipment']);
        $this->shipment = Shipment::factory()->create([
            'status_id' => $this->status->id,
            'priority' => 'low',
        ]);
    }

    public function test_can_update_shipment_with_valid_data(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/shipments/{$this->shipment->id}", [
                'priority' => 'urgent',
                'reference_number' => 'REF-7777',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shipment updated successfully',
            ])
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.reference_number', 'REF-7777');

        $this->assertDatabaseHas('shipments', [
            'id' => $this->shipment->id,
            'priority' => 'urgent',
            'reference_number' => 'REF-7777',
        ]);
    }

    public function test_returns_no_changes_when_unchanged(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/shipments/{$this->shipment->id}", [
                'priority' => $this->shipment->priority,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'No changes detected',
            ]);
    }

    public function test_validation_fails_with_invalid_status_id(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/shipments/{$this->shipment->id}", [
                'status_id' => 999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_id']);
    }
}
