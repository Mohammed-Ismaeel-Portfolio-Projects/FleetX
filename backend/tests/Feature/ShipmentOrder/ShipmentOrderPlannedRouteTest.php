<?php

namespace Tests\Feature\ShipmentOrder;

use App\Actions\ShipmentOrder\CalculateShipmentOrderPlannedRouteAction;

use App\Models\Company;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentOrderPlannedRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;
    private ShipmentOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\ShipmentOrderStatusesSeeder::class);

        /** @var Status $draftStatus */
        $draftStatus = Status::query()->where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::DRAFT)->firstOrFail();

        $dispatcherRole = \App\Models\Role::where('name', 'dispatcher')->firstOrFail();
        $company = Company::factory()->create(['customer_type' => \App\Enums\CustomerType::STANDARD]);
        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'company_id' => $company->id,
        ]);

        $this->order = ShipmentOrder::query()->create([
            'request_number' => 'REQ-TEST-ROUTE-001',
            'customer_company_id' => $company->id,
            'status_id' => $draftStatus->id,
            'pickup_latitude' => 33.5138000,
            'pickup_longitude' => 36.2765000,
            'delivery_latitude' => 33.8938000,
            'delivery_longitude' => 35.5018000,
        ]);
    }

    public function test_calculate_planned_route_persists_geometry_distance_and_duration(): void
    {
        /** @var CalculateShipmentOrderPlannedRouteAction $action */
        $action = app(CalculateShipmentOrderPlannedRouteAction::class);
        $updated = $action->execute($this->order);

        $this->assertNotNull($updated->planned_route_status);
        $this->assertContains($updated->planned_route_status, ['AVAILABLE', 'UNAVAILABLE']);
        $this->assertEquals(33.5138000, (float) $updated->planned_route_origin_latitude);
        $this->assertEquals(36.2765000, (float) $updated->planned_route_origin_longitude);

        if ($updated->planned_route_status === 'AVAILABLE') {
            $this->assertGreaterThan(0, $updated->planned_distance_meters);
            $this->assertGreaterThan(0, $updated->planned_duration_seconds);
            $this->assertIsArray($updated->planned_route_geometry);
            $this->assertNotEmpty($updated->planned_route_geometry);
        }
    }

    public function test_route_snapshot_is_reused_when_coordinates_unmodified(): void
    {
        $this->order->update([
            'planned_distance_meters' => 125000,
            'planned_duration_seconds' => 5400,
            'planned_route_geometry' => [[33.5138, 36.2765], [33.8938, 35.5018]],
            'planned_route_origin_latitude' => 33.5138000,
            'planned_route_origin_longitude' => 36.2765000,
            'planned_route_destination_latitude' => 33.8938000,
            'planned_route_destination_longitude' => 35.5018000,
            'planned_route_status' => 'AVAILABLE',
            'planned_route_calculated_at' => now(),
        ]);

        /** @var CalculateShipmentOrderPlannedRouteAction $action */
        $action = app(CalculateShipmentOrderPlannedRouteAction::class);
        $result = $action->execute($this->order);

        $this->assertEquals(125000, $result->planned_distance_meters);
        $this->assertEquals(5400, $result->planned_duration_seconds);
        $this->assertEquals([[33.5138, 36.2765], [33.8938, 35.5018]], $result->planned_route_geometry);
    }

    public function test_route_snapshot_is_invalidated_when_coordinates_change(): void
    {
        $this->order->update([
            'planned_distance_meters' => 125000,
            'planned_duration_seconds' => 5400,
            'planned_route_geometry' => [[33.5138, 36.2765], [33.8938, 35.5018]],
            'planned_route_origin_latitude' => 33.5138000,
            'planned_route_origin_longitude' => 36.2765000,
            'planned_route_destination_latitude' => 33.8938000,
            'planned_route_destination_longitude' => 35.5018000,
            'planned_route_status' => 'AVAILABLE',
            'planned_route_calculated_at' => now(),
        ]);

        // Change delivery coordinates
        $this->order->update([
            'delivery_latitude' => 34.0000000,
            'delivery_longitude' => 36.0000000,
        ]);

        /** @var CalculateShipmentOrderPlannedRouteAction $action */
        $action = app(CalculateShipmentOrderPlannedRouteAction::class);
        $result = $action->execute($this->order);

        $this->assertEquals(34.0000000, (float) $result->planned_route_destination_latitude);
        $this->assertEquals(36.0000000, (float) $result->planned_route_destination_longitude);
    }

    public function test_dispatcher_show_endpoint_returns_planned_route_payload(): void
    {
        $response = $this->actingAs($this->dispatcherUser)
            ->getJson("/api/v1/shipment-requests/{$this->order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->order->id)
            ->assertJsonStructure([
                'data' => [
                    'planned_route' => [
                        'status',
                        'distance_meters',
                        'distance_km',
                        'duration_seconds',
                        'duration_formatted',
                        'geometry',
                        'calculated_at',
                    ],
                ],
            ]);
    }
}
