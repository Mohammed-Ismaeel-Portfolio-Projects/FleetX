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
 * C2.9-C1.5 — UpdateShipmentAction status enforcement via transition boundary.
 */
class ShipmentUpdateStatusEnforcementTest extends TestCase
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

    public function test_update_without_status_id_continues_to_work(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT, ['priority' => 'low']);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'priority' => 'urgent',
                'reference_number' => 'C15-REF-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.reference_number', 'C15-REF-001');

        $fresh = $shipment->fresh();
        $this->assertSame('urgent', $fresh->priority);
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $fresh->status->name);
    }

    public function test_update_with_waiting_assignment_to_assigned_succeeds(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Shipment updated successfully',
            ]);

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
    }

    public function test_unsupported_status_change_via_update_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::IN_TRANSIT);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
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

    public function test_draft_to_assigned_via_update_is_rejected(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::DRAFT);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertStatus(422);

        $this->assertSame(ShipmentStatus::DRAFT, $shipment->fresh()->status->name);
    }

    public function test_failed_status_transition_does_not_persist_other_fields(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::IN_TRANSIT, [
            'priority' => 'low',
            'reference_number' => 'C15-KEEP',
        ]);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'priority' => 'urgent',
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertStatus(422);

        $fresh = $shipment->fresh();
        $this->assertSame('low', $fresh->priority);
        $this->assertSame('C15-KEEP', $fresh->reference_number);
        $this->assertSame(ShipmentStatus::IN_TRANSIT, $fresh->status->name);
    }

    public function test_admin_cannot_perform_combined_status_and_field_update(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id, 'password' => Hash::make('password')]);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT, ['priority' => 'normal']);

        $this->actingAs($admin, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'priority' => 'high',
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertForbidden();

        $fresh = $shipment->fresh();
        $this->assertSame('normal', $fresh->priority);
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $fresh->status->name);
    }

    public function test_dispatcher_combined_supported_status_and_field_update_succeeds(): void
    {
        $dispatcher = $this->makeDispatcher();
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT, ['priority' => 'normal']);

        $this->actingAs($dispatcher, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'priority' => 'high',
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertOk();

        $fresh = $shipment->fresh();
        $this->assertSame('high', $fresh->priority);
        $this->assertSame(ShipmentStatus::ASSIGNED, $fresh->status->name);
    }

    public function test_client_is_denied_on_shipment_update(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);
        $shipment = $this->makeShipmentInStatus(ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($client, 'api')
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'priority' => 'urgent',
            ])
            ->assertForbidden();
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
