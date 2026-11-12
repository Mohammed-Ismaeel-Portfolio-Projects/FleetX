<?php

namespace Tests\Feature\Shipment;

use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreShipmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $status = Status::create(['name' => 'Draft', 'type' => 'shipment']);
        $this->shipment = Shipment::factory()->create(['status_id' => $status->id]);
        $this->shipment->delete();
    }

    public function test_can_restore_deleted_shipment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/shipments/{$this->shipment->id}/restore");

        $response->assertStatus(200)
            ->assertJson([ 
                'success' => true,
                'message' => 'Shipment restored successfully',
            ]);

        $this->assertDatabaseHas('shipments', [
            'id' => $this->shipment->id,
            'deleted_at' => null,
        ]);
    }

    public function test_returns_error_when_shipment_is_not_deleted(): void
    {
        $activeShipment = Shipment::factory()->create(['status_id' => Status::create(['name' => 'Waiting Assignment', 'type' => 'shipment'])->id]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/shipments/{$activeShipment->id}/restore");

        $response->assertStatus(400)
            ->assertJson([ 
                'success' => false,
                'message' => 'Shipment is not deleted',
            ]);
    }

    public function test_returns_404_for_non_existent_shipment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->patchJson('/api/v1/shipments/550e8400-e29b-41d4-a716-446655440000/restore');

        $response->assertStatus(404);
    }
}
