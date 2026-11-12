<?php

namespace Tests\Feature\Shipment;

use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteShipmentTest extends TestCase
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
    }

    public function test_can_soft_delete_shipment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/shipments/{$this->shipment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Shipment deleted successfully',
            ]);

        $this->assertSoftDeleted('shipments', ['id' => $this->shipment->id]);
    }

    public function test_returns_404_for_non_existent_shipment(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->deleteJson('/api/v1/shipments/550e8400-e29b-41d4-a716-446655440000');

        $response->assertStatus(404);
    }
}
