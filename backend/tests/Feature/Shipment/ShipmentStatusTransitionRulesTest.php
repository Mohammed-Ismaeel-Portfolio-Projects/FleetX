<?php

namespace Tests\Feature\Shipment;

use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Exceptions\InvalidShipmentTransitionException;
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
 * C2.9-C1.4 — Shipment status transition boundary.
 */
class ShipmentStatusTransitionRulesTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $clientRole;

    private TransitionShipmentStatusAction $transitionAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->transitionAction = app(TransitionShipmentStatusAction::class);
    }

    public function test_waiting_assignment_to_assigned_succeeds_via_api(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Shipment status changed successfully',
            ]);

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
    }

    public function test_unsupported_transition_is_rejected_via_api(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->actingAs($dispatcher, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('errors.from', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('errors.to', ShipmentStatus::ASSIGNED);

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
    }

    public function test_transition_action_rejects_draft_to_assigned(): void
    {
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::DRAFT);

        $this->expectException(InvalidShipmentTransitionException::class);

        $this->transitionAction->execute($shipment, ShipmentStatus::ASSIGNED, 1);
    }

    public function test_admin_cannot_change_status_on_supported_transition(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertForbidden();

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
    }

    public function test_client_is_denied_on_change_status_endpoint(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($client, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertForbidden();

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
    }

    public function test_unauthenticated_change_status_is_rejected(): void
    {
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->patchJson("/api/v1/shipments/{$shipment->id}/status", [
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
        ])->assertUnauthorized();
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeShipmentInStatus(string $statusName): Shipment
    {
        return Shipment::factory()->create([
            'status_id' => ShipmentStatus::statusId($statusName),
        ]);
    }
}
