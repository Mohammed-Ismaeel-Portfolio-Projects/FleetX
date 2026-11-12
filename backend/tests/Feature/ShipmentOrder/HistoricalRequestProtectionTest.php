<?php

namespace Tests\Feature\ShipmentOrder;

use App\Models\Role;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalRequestProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);

        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->dispatcher = User::where('email', 'dispatcher@fleetx.io')->first()
            ?? User::factory()->create(['role_id' => $dispatcherRole->id]);
    }

    public function test_cannot_approve_historical_approved_request(): void
    {
        $approvedStatus = Status::where('name', ShipmentOrderStatus::APPROVED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $request = ShipmentOrder::first();
        $request->update(['status_id' => $approvedStatus->id]);

        $response = $this->actingAs($this->dispatcher, 'api')
            ->postJson("/api/v1/shipment-requests/{$request->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Historical shipment requests are immutable and cannot be modified.',
            ]);
    }

    public function test_cannot_reject_historical_converted_request(): void
    {
        $convertedStatus = Status::where('name', ShipmentOrderStatus::CONVERTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $request = ShipmentOrder::first();
        $request->update(['status_id' => $convertedStatus->id]);

        $response = $this->actingAs($this->dispatcher, 'api')
            ->postJson("/api/v1/shipment-requests/{$request->id}/reject");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Historical shipment requests are immutable and cannot be modified.',
            ]);
    }

    public function test_cannot_request_changes_on_historical_request(): void
    {
        $rejectedStatus = Status::where('name', ShipmentOrderStatus::REJECTED)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->firstOrFail();

        $request = ShipmentOrder::first();
        $request->update(['status_id' => $rejectedStatus->id]);

        $response = $this->actingAs($this->dispatcher, 'api')
            ->postJson("/api/v1/shipment-requests/{$request->id}/request-changes", [
                'reason' => 'Need additional documents',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Historical shipment requests are immutable and cannot be modified.',
            ]);
    }
}
