<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\TrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DispatcherTrackingSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;
    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\ShipmentOrderStatusesSeeder::class);

        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $company = Company::factory()->create();

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'company_id' => $company->id,
        ]);

        $this->clientUser = User::factory()->create([
            'role_id' => $clientRole->id,
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

    public function test_dispatcher_can_start_tracking_session(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson('/api/v1/dispatcher/tracking-sessions/start', [
                'shipment_order_id' => $order->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shipment_order_id', $order->id)
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertDatabaseHas('tracking_sessions', [
            'shipment_order_id' => $order->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_starting_existing_session_returns_same_session(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();

        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'assignment_id' => $assignment->id,
            'status' => 'INACTIVE',
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson('/api/v1/dispatcher/tracking-sessions/start', [
                'shipment_order_id' => $order->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_cannot_start_shipment_without_assignment(): void
    {
        $order = ShipmentOrder::factory()->create([
            'converted_shipment_id' => null,
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson('/api/v1/dispatcher/tracking-sessions/start', [
                'shipment_order_id' => $order->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_active_session_can_pause(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();
        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'status' => 'ACTIVE',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson("/api/v1/dispatcher/tracking-sessions/{$session->id}/pause");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'PAUSED');

        $this->assertEquals('PAUSED', $session->fresh()->status);
    }

    public function test_paused_session_can_resume(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();
        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'status' => 'PAUSED',
            'paused_at' => now(),
        ]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson("/api/v1/dispatcher/tracking-sessions/{$session->id}/resume");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertEquals('ACTIVE', $session->fresh()->status);
    }

    public function test_active_session_can_stop(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();
        $session = TrackingSession::create([
            'shipment_order_id' => $order->id,
            'status' => 'ACTIVE',
        ]);
        Cache::put("tracking_session:{$session->id}:progress", ['index' => 50]);

        $response = $this->actingAs($this->dispatcherUser)
            ->postJson("/api/v1/dispatcher/tracking-sessions/{$session->id}/stop");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'TERMINATED');

        $this->assertEquals('TERMINATED', $session->fresh()->status);
        $this->assertFalse(Cache::has("tracking_session:{$session->id}:progress"));
    }

    public function test_override_speed_persists(): void
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

        $response->assertStatus(200)
            ->assertJsonPath('data.speed_override', 120);

        $this->assertEquals(120, (float) $session->fresh()->forced_speed_kmh);
    }

    public function test_override_route_deviation_persists(): void
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

        $response->assertStatus(200)
            ->assertJsonPath('data.forced_route_deviation', true);

        $this->assertTrue($session->fresh()->forced_route_deviation);
    }

    public function test_unauthorized_user_rejected(): void
    {
        [$order, $shipment, $assignment] = $this->createOrderWithAssignment();

        $response = $this->actingAs($this->clientUser)
            ->postJson('/api/v1/dispatcher/tracking-sessions/start', [
                'shipment_order_id' => $order->id,
            ]);

        $response->assertStatus(403);
    }
}
