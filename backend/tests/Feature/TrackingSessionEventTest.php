<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\TrackingSession;
use App\Models\TrackingSessionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingSessionEventTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\ShipmentOrderStatusesSeeder::class);

        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $company = Company::factory()->create();

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'company_id' => $company->id,
        ]);
    }

    private function createOrderWithAssignment(): array
    {
        $shipment = Shipment::factory()->create();
        $driver = Driver::factory()->create();
        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'is_active' => true,
        ]);

        $order = ShipmentOrder::factory()->create([
            'converted_shipment_id' => $shipment->id,
        ]);

        return [$order, $shipment, $assignment];
    }

    public function test_starting_session_creates_started_event(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson('/api/v1/dispatcher/tracking-sessions/start', [
                'shipment_order_id' => $order->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tracking_session_events', [
            'event_type' => TrackingSessionEvent::EVENT_STARTED,
        ]);
    }

    public function test_pause_creates_paused_event(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();
        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson("/api/v1/dispatcher/tracking-sessions/{$session->id}/pause");

        $response->assertStatus(200);

        $this->assertDatabaseHas('tracking_session_events', [
            'tracking_session_id' => $session->id,
            'event_type' => TrackingSessionEvent::EVENT_PAUSED,
        ]);
    }

    public function test_override_speed_creates_speed_override_event(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();
        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson("/api/v1/dispatcher/tracking-sessions/{$session->id}/override", [
                'forced_speed_kmh' => 120,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tracking_session_events', [
            'tracking_session_id' => $session->id,
            'event_type' => TrackingSessionEvent::EVENT_SPEED_OVERRIDE,
        ]);
    }

    public function test_sudden_stop_creates_event(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();
        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson("/api/v1/dispatcher/tracking-sessions/{$session->id}/override", [
                'forced_deceleration' => true,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tracking_session_events', [
            'tracking_session_id' => $session->id,
            'event_type' => TrackingSessionEvent::EVENT_SUDDEN_STOP_TRIGGERED,
        ]);
    }

    public function test_route_deviation_creates_event(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();
        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson("/api/v1/dispatcher/tracking-sessions/{$session->id}/override", [
                'forced_route_deviation' => true,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tracking_session_events', [
            'tracking_session_id' => $session->id,
            'event_type' => TrackingSessionEvent::EVENT_ROUTE_DEVIATION_TRIGGERED,
        ]);
    }

    public function test_events_endpoint_returns_history(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();
        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'status' => 'ACTIVE',
        ]);

        TrackingSessionEvent::create([
            'tracking_session_id' => $session->id,
            'event_type' => TrackingSessionEvent::EVENT_STARTED,
        ]);

        TrackingSessionEvent::create([
            'tracking_session_id' => $session->id,
            'event_type' => TrackingSessionEvent::EVENT_SPEED_OVERRIDE,
            'payload' => ['forced_speed_kmh' => 110],
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->getJson("/api/v1/dispatcher/tracking-sessions/{$session->id}/events");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event_type', TrackingSessionEvent::EVENT_SPEED_OVERRIDE)
            ->assertJsonPath('data.1.event_type', TrackingSessionEvent::EVENT_STARTED);
    }
}
