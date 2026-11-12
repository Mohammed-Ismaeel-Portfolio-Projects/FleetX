<?php

namespace Tests\Feature\Trailer;

use App\Actions\Assignment\CreateAssignmentAction;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligentTrailerAssignmentFilteringTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcher;
    private Status $asgStatus;
    private Status $shpAssignedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'dispatcher']);
        $this->dispatcher = User::factory()->create(['role_id' => $role->id]);
        $this->dispatcher->setRelation('role', $role);

        $this->asgStatus = Status::firstOrCreate(['name' => 'Assigned', 'type' => 'assignment']);
        $this->shpAssignedStatus = Status::firstOrCreate(['name' => 'Assigned', 'type' => 'shipment']);
    }

    public function test_requested_trailer_type_prioritizes_matching_trailers_first(): void
    {
        $reefer = Trailer::factory()->create(['trailer_type' => 'reefer', 'is_active' => true]);
        $dryVan = Trailer::factory()->create(['trailer_type' => 'dry_van', 'is_active' => true]);

        $response = $this->actingAs($this->dispatcher, 'api')
            ->getJson('/api/v1/trailers?available_only=true&requested_trailer_type=reefer');

        $response->assertStatus(200);
        $items = $response->json('data.data');

        $this->assertCount(2, $items);
        $this->assertEquals($reefer->id, $items[0]['id']);
        $this->assertTrue($items[0]['is_matching_requested_type']);
        $this->assertFalse($items[1]['is_matching_requested_type']);
    }

    public function test_available_only_excludes_inactive_or_actively_assigned_trailers(): void
    {
        $activeTrailer = Trailer::factory()->create(['trailer_type' => 'reefer', 'is_active' => true]);
        $inactiveTrailer = Trailer::factory()->create(['trailer_type' => 'reefer', 'is_active' => false]);
        $busyTrailer = Trailer::factory()->create(['trailer_type' => 'reefer', 'is_active' => true]);

        $shipment = Shipment::factory()->create();
        $driver = Driver::factory()->create();
        Assignment::create([
            'assignment_code' => 'ASG-BUSY-001',
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'trailer_id' => $busyTrailer->id,
            'status_id' => $this->asgStatus->id,
            'assigned_by' => $this->dispatcher->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->dispatcher, 'api')
            ->getJson('/api/v1/trailers?available_only=true');

        $response->assertStatus(200);
        $items = $response->json('data.data');

        $ids = collect($items)->pluck('id')->all();
        $this->assertContains($activeTrailer->id, $ids);
        $this->assertNotContains($inactiveTrailer->id, $ids);
        $this->assertNotContains($busyTrailer->id, $ids);
    }

    public function test_assignment_with_matching_trailer_succeeds_and_preserves_request(): void
    {
        $order = ShipmentOrder::factory()->create(['trailer_type' => 'reefer']);
        $shipment = Shipment::factory()->create(['shipment_order_id' => $order->id]);
        $trailer = Trailer::factory()->create(['trailer_type' => 'reefer', 'is_active' => true]);
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $action = app(CreateAssignmentAction::class);
        $result = $action->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-MATCH-001',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('reefer', $order->fresh()->trailer_type);
        $this->assertEquals($trailer->id, $result->data->trailer_id);
    }

    public function test_assignment_with_non_matching_trailer_operational_override_succeeds_and_preserves_request(): void
    {
        $order = ShipmentOrder::factory()->create(['trailer_type' => 'reefer']);
        $shipment = Shipment::factory()->create(['shipment_order_id' => $order->id]);
        $trailer = Trailer::factory()->create(['trailer_type' => 'dry_van', 'is_active' => true]);
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $action = app(CreateAssignmentAction::class);
        $result = $action->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-OVERRIDE-001',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($result->success);
        // Requirement remains permanently reefer
        $this->assertEquals('reefer', $order->fresh()->trailer_type);
        // Actual assignment points to dry_van trailer
        $this->assertEquals($trailer->id, $result->data->trailer_id);
        $this->assertEquals('dry_van', $trailer->fresh()->trailer_type);
        // Preference mismatch is dynamically detectable
        $this->assertNotEquals($order->fresh()->trailer_type, $trailer->fresh()->trailer_type);
    }

    public function test_server_prevents_double_assignment_of_trailer(): void
    {
        $trailer = Trailer::factory()->create(['trailer_type' => 'reefer', 'is_active' => true]);
        $shipment1 = Shipment::factory()->create();
        $shipment2 = Shipment::factory()->create();
        $driver1 = Driver::factory()->create();
        $driver2 = Driver::factory()->create();

        $action = app(CreateAssignmentAction::class);
        $res1 = $action->execute([
            'shipment_id' => $shipment1->id,
            'assignment_code' => 'ASG-DOUBLE-001',
            'driver_id' => $driver1->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($res1->success);

        $res2 = $action->execute([
            'shipment_id' => $shipment2->id,
            'assignment_code' => 'ASG-DOUBLE-002',
            'driver_id' => $driver2->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->dispatcher->id,
            'status_id' => $this->asgStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($res2->success);
        $this->assertEquals('Trailer is already assigned to an active assignment', $res2->message);
    }
}
