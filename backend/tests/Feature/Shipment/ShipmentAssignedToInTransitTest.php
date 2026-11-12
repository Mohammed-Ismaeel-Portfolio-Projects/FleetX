<?php

namespace Tests\Feature\Shipment;

use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C2.9-C1.6 — Assigned → In Transit Shipment transition.
 */
class ShipmentAssignedToInTransitTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
    }

    public function test_assigned_to_in_transit_succeeds_via_status_endpoint(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Shipment status changed successfully',
            ]);

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
    }

    public function test_assigned_to_in_transit_succeeds_via_update_endpoint(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Shipment updated successfully',
            ]);

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
    }

    public function test_waiting_assignment_to_in_transit_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.from', ShipmentStatus::WAITING_ASSIGNMENT)
            ->assertJsonPath('errors.to', ShipmentStatus::IN_TRANSIT);

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
    }

    public function test_draft_to_in_transit_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::DRAFT);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
            ])
            ->assertStatus(422);

        $this->assertSame(ShipmentStatus::DRAFT, $shipment->fresh()->status->name);
    }

    public function test_in_transit_to_assigned_remains_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.from', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('errors.to', ShipmentStatus::ASSIGNED);

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
    }

    public function test_admin_cannot_transition_assigned_to_in_transit(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
            ])
            ->assertForbidden();

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
    }

    public function test_client_is_denied_for_assigned_to_in_transit(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->actingAs($client, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
            ])
            ->assertForbidden();

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
    }

    public function test_unauthenticated_assigned_to_in_transit_is_rejected(): void
    {
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::ASSIGNED);

        $this->patchJson("/api/v1/shipments/{$shipment->id}/status", [
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::IN_TRANSIT),
        ])->assertUnauthorized();

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
    }

    public function test_waiting_assignment_to_assigned_remains_unchanged(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertOk();

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
    }

    public function test_invalid_transition_does_not_partially_update_other_fields(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::ASSIGNED, [
            'priority' => 'low',
            'reference_number' => 'C16-KEEP',
        ]);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'priority' => 'urgent',
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::DELIVERED),
            ])
            ->assertStatus(422);

        $fresh = $shipment->fresh();
        $this->assertSame('low', $fresh->priority);
        $this->assertSame('C16-KEEP', $fresh->reference_number);
        $this->assertSame(ShipmentStatus::ASSIGNED, $fresh->status->name);
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeShipmentInStatus(string $statusName, array $overrides = []): Shipment
    {
        return Shipment::factory()->create(array_merge([
            'status_id' => ShipmentStatus::statusId($statusName),
        ], $overrides));
    }
}
