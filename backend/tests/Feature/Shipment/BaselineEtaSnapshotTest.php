<?php

namespace Tests\Feature\Shipment;

use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Actions\ShipmentOrder\CalculateShipmentOrderPlannedRouteAction;
use App\Actions\ShipmentOrder\ConvertShipmentOrderToShipmentAction;
use App\Models\Company;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\EngineEnrollmentStatus;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BaselineEtaSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;
    private Company $customerCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBaseStatuses();

        $dispatcherRole = Role::firstOrCreate(['name' => 'dispatcher']);

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'password' => Hash::make('password123'),
        ]);

        $activeStatus = Status::firstOrCreate(
            ['name' => 'active', 'type' => 'company'],
            ['name' => 'active', 'type' => 'company']
        );

        $this->customerCompany = Company::create([
            'company_code' => 'CMP-BASE-001',
            'company_name' => 'Test Baseline Company',
            'legal_name' => 'Test Baseline Company Ltd',
            'status_id' => $activeStatus->id,
            'primary_email' => 'test@baseline.com',
            'primary_phone' => '+963 11 123456',
            'address_line_1' => 'Main Street 1',
            'postal_code' => '10000',
            'city' => 'Damascus',
            'country' => 'SY',
            'currency_code' => 'SYP',
            'is_active' => true,
        ]);
    }

    private function seedBaseStatuses(): void
    {
        foreach (ShipmentOrderStatus::all() as $name) {
            Status::firstOrCreate(
                ['name' => $name, 'type' => ShipmentOrderStatus::TYPE],
                ['name' => $name, 'type' => ShipmentOrderStatus::TYPE]
            );
        }

        $shpStatuses = [
            ShipmentStatus::APPROVED,
            ShipmentStatus::WAITING_ASSIGNMENT,
            ShipmentStatus::ASSIGNED,
            ShipmentStatus::READY_FOR_PICKUP,
            ShipmentStatus::PICKED_UP,
            ShipmentStatus::IN_TRANSIT,
            ShipmentStatus::AT_CUSTOMS,
            ShipmentStatus::OUT_FOR_DELIVERY,
            ShipmentStatus::DELIVERED,
            ShipmentStatus::COMPLETED,
            ShipmentStatus::CANCELLED,
        ];

        foreach ($shpStatuses as $name) {
            Status::firstOrCreate(
                ['name' => $name, 'type' => ShipmentStatus::TYPE],
                ['name' => $name, 'type' => ShipmentStatus::TYPE]
            );
        }

        Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );
    }

    private function createApprovedOrderWithRoute(int $durationSeconds = 7200, int $distanceMeters = 150000): ShipmentOrder
    {
        $approvedStatus = Status::where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::APPROVED)->firstOrFail();

        return ShipmentOrder::create([
            'request_number' => 'REQ-TEST-' . uniqid(),
            'customer_company_id' => $this->customerCompany->id,
            'submitted_by_user_id' => $this->dispatcherUser->id,
            'status_id' => $approvedStatus->id,
            'submission_revision' => 1,
            'reference_number' => 'REF-TEST-001',
            'cargo_type' => 'General Freight',
            'cargo_weight' => 12000.00,
            'cargo_volume' => 45.00,
            'package_count' => 20,
            'pickup_company' => 'Damascus Logistics',
            'pickup_address' => 'Baghdad Street',
            'pickup_city' => 'Damascus',
            'pickup_country' => 'SY',
            'pickup_latitude' => 33.5138,
            'pickup_longitude' => 36.2765,
            'delivery_company' => 'Aleppo Freight Hub',
            'delivery_address' => 'Industrial Zone',
            'delivery_city' => 'Aleppo',
            'delivery_country' => 'SY',
            'delivery_latitude' => 36.2021,
            'delivery_longitude' => 37.1343,
            'requested_pickup_date' => now()->format('Y-m-d'),
            'requested_delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'planned_duration_seconds' => $durationSeconds,
            'planned_distance_meters' => $distanceMeters,
            'planned_route_status' => 'AVAILABLE',
            'planned_route_calculated_at' => now()->subMinutes(10),
            'priority' => 'normal',
        ]);
    }

    public function test_shipment_conversion_freezes_baseline_eta_snapshots(): void
    {
        $order = $this->createApprovedOrderWithRoute(7200, 150000);

        /** @var ConvertShipmentOrderToShipmentAction $action */
        $action = app(ConvertShipmentOrderToShipmentAction::class);
        $result = $action->execute($this->dispatcherUser, $order->id);

        $this->assertTrue($result->success);

        /** @var Shipment $shipment */
        $shipment = $result->data['shipment'];
        $shipment = $shipment->fresh();

        $this->assertNotNull($shipment);
        $this->assertEquals(7200, $shipment->baseline_eta_seconds);
        $this->assertEquals(150000, $shipment->baseline_distance_meters);
        $this->assertNotNull($shipment->baseline_routing_provider);
        $this->assertNotNull($shipment->baseline_calculated_at);
        $this->assertEquals(
            $order->planned_route_calculated_at->toDateTimeString(),
            $shipment->baseline_calculated_at->toDateTimeString()
        );
    }

    public function test_actual_pickup_at_timestamp_is_persisted_on_in_transit_transition(): void
    {
        $order = $this->createApprovedOrderWithRoute();
        $convertResult = app(ConvertShipmentOrderToShipmentAction::class)->execute($this->dispatcherUser, $order->id);
        /** @var Shipment $shipment */
        $shipment = $convertResult->data['shipment'];

        $this->assertNull($shipment->actual_pickup_at);

        /** @var TransitionShipmentStatusAction $transitionAction */
        $transitionAction = app(TransitionShipmentStatusAction::class);
        $assignedShipment = $transitionAction->execute(
            $shipment,
            ShipmentStatus::ASSIGNED,
            $this->dispatcherUser->id
        );

        $updatedShipment = $transitionAction->execute(
            $assignedShipment,
            ShipmentStatus::IN_TRANSIT,
            $this->dispatcherUser->id,
            now()->toDateString()
        );

        $this->assertNotNull($updatedShipment->actual_pickup_at);
        $firstPickupAt = $updatedShipment->actual_pickup_at->toDateTimeString();

        // Ensure idempotency: subsequent status transitions do NOT overwrite the original pickup timestamp
        $deliveredShipment = $transitionAction->execute(
            $updatedShipment,
            ShipmentStatus::DELIVERED,
            $this->dispatcherUser->id
        );

        $this->assertEquals($firstPickupAt, $deliveredShipment->actual_pickup_at->toDateTimeString());
    }

    public function test_actual_delivery_at_timestamp_is_persisted_on_delivery_transition(): void
    {
        $order = $this->createApprovedOrderWithRoute();
        $convertResult = app(ConvertShipmentOrderToShipmentAction::class)->execute($this->dispatcherUser, $order->id);
        /** @var Shipment $shipment */
        $shipment = $convertResult->data['shipment'];

        /** @var TransitionShipmentStatusAction $transitionAction */
        $transitionAction = app(TransitionShipmentStatusAction::class);
        $assignedShipment = $transitionAction->execute(
            $shipment,
            ShipmentStatus::ASSIGNED,
            $this->dispatcherUser->id
        );

        $inTransitShipment = $transitionAction->execute(
            $assignedShipment,
            ShipmentStatus::IN_TRANSIT,
            $this->dispatcherUser->id,
            now()->toDateString()
        );

        $this->assertNull($inTransitShipment->actual_delivery_at);

        $deliveredShipment = $transitionAction->execute(
            $inTransitShipment,
            ShipmentStatus::DELIVERED,
            $this->dispatcherUser->id,
            null,
            now()->toDateString()
        );

        $this->assertNotNull($deliveredShipment->actual_delivery_at);
        $firstDeliveryAt = $deliveredShipment->actual_delivery_at->toDateTimeString();

        // Completing the shipment preserves the original delivery timestamp
        $completedShipment = $transitionAction->execute(
            $deliveredShipment,
            ShipmentStatus::COMPLETED,
            $this->dispatcherUser->id
        );

        $this->assertEquals($firstDeliveryAt, $completedShipment->actual_delivery_at->toDateTimeString());
    }

    public function test_baseline_eta_snapshots_remain_immutable_during_status_transitions(): void
    {
        $order = $this->createApprovedOrderWithRoute(5400, 120000);
        $convertResult = app(ConvertShipmentOrderToShipmentAction::class)->execute($this->dispatcherUser, $order->id);
        /** @var Shipment $shipment */
        $shipment = $convertResult->data['shipment'];

        $originalBaselineEta = $shipment->baseline_eta_seconds;
        $originalBaselineDist = $shipment->baseline_distance_meters;
        $originalProvider = $shipment->baseline_routing_provider;
        $originalCalculatedAt = $shipment->baseline_calculated_at->toDateTimeString();

        /** @var TransitionShipmentStatusAction $transitionAction */
        $transitionAction = app(TransitionShipmentStatusAction::class);
        $assignedShipment = $transitionAction->execute(
            $shipment,
            ShipmentStatus::ASSIGNED,
            $this->dispatcherUser->id
        );

        $inTransitShipment = $transitionAction->execute(
            $assignedShipment,
            ShipmentStatus::IN_TRANSIT,
            $this->dispatcherUser->id
        );

        $deliveredShipment = $transitionAction->execute(
            $inTransitShipment,
            ShipmentStatus::DELIVERED,
            $this->dispatcherUser->id
        );

        $completedShipment = $transitionAction->execute(
            $deliveredShipment,
            ShipmentStatus::COMPLETED,
            $this->dispatcherUser->id
        );

        $this->assertEquals($originalBaselineEta, $completedShipment->baseline_eta_seconds);
        $this->assertEquals($originalBaselineDist, $completedShipment->baseline_distance_meters);
        $this->assertEquals($originalProvider, $completedShipment->baseline_routing_provider);
        $this->assertEquals($originalCalculatedAt, $completedShipment->baseline_calculated_at->toDateTimeString());
    }
}
