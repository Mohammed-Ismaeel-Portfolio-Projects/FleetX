<?php

namespace Tests\Feature\Notification;

use App\Actions\Assignment\CreateAssignmentAction;
use App\Actions\Shipment\CreateShipmentAction;
use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Exceptions\InvalidShipmentTransitionException;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Simulation\Lifecycle\ShipmentLifecycleStage;
use App\Simulation\Lifecycle\ShipmentLifecycleTransitionAdapter;
use App\Support\ClientNotificationAction;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C4.2-A2.3 — shipment status / progress Client notification hooks.
 */
class ShipmentStatusClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    private TransitionShipmentStatusAction $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->transition = app(TransitionShipmentStatusAction::class);
    }

    public function test_assigned_creates_shipment_details_notification_with_shipment_uuid(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::WAITING_ASSIGNMENT);

        $this->transition->execute($shipment, ShipmentStatus::ASSIGNED, $this->makeDispatcher()->id);

        $this->assertSame(1, Notification::query()->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => $shipment->id,
            'title' => 'Shipment assigned',
            'message' => 'Your shipment has been assigned and is progressing toward fulfillment.',
        ]);
    }

    public function test_in_transit_creates_shipment_progress_notification_with_shipment_uuid(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::ASSIGNED);

        $this->transition->execute($shipment, ShipmentStatus::IN_TRANSIT, $this->makeDispatcher()->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_PROGRESS,
            'action_id' => $shipment->id,
            'title' => 'Shipment in transit',
            'message' => 'Your shipment is now in transit.',
        ]);
    }

    public function test_delivered_creates_shipment_progress_notification(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::IN_TRANSIT);

        $this->transition->execute($shipment, ShipmentStatus::DELIVERED, $this->makeDispatcher()->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_PROGRESS,
            'action_id' => $shipment->id,
            'title' => 'Shipment delivered',
            'message' => 'Your shipment has been delivered.',
        ]);
    }

    public function test_completed_creates_shipment_details_notification(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::DELIVERED);

        $this->transition->execute($shipment, ShipmentStatus::COMPLETED, $this->makeDispatcher()->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => $shipment->id,
            'title' => 'Shipment completed',
            'message' => 'Your shipment has been completed.',
        ]);
    }

    public function test_same_company_client_receives_and_other_company_client_does_not(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $clientB = $this->makeClient($companyB);
        $shipment = $this->makeShipment($companyA, ShipmentStatus::WAITING_ASSIGNMENT);

        $this->transition->execute($shipment, ShipmentStatus::ASSIGNED, $this->makeDispatcher()->id);

        $this->assertDatabaseHas('notifications', ['user_id' => $clientA->id, 'action_id' => $shipment->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $clientB->id]);
        $this->assertSame(0, Notification::query()->where('user_id', $clientB->id)->count());
    }

    public function test_dispatcher_admin_and_driver_receive_zero_client_inbox_notifications(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $dispatcher = $this->makeCompanyUser($company, $this->dispatcherRole);
        $admin = $this->makeCompanyUser($company, $this->adminRole);
        $driver = $this->makeCompanyUser($company, $this->driverRole);
        $shipment = $this->makeShipment($company, ShipmentStatus::WAITING_ASSIGNMENT);

        $this->transition->execute($shipment, ShipmentStatus::ASSIGNED, $dispatcher->id);

        $this->assertDatabaseMissing('notifications', ['user_id' => $dispatcher->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $driver->id]);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_company_with_no_client_users_still_transitions_with_zero_notifications(): void
    {
        $company = Company::factory()->create();
        $this->makeCompanyUser($company, $this->dispatcherRole);
        $shipment = $this->makeShipment($company, ShipmentStatus::WAITING_ASSIGNMENT);

        $updated = $this->transition->execute($shipment, ShipmentStatus::ASSIGNED, $this->makeDispatcher()->id);

        $this->assertSame(ShipmentStatus::ASSIGNED, $updated->status->name);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_invalid_transition_creates_zero_notifications(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::DRAFT);

        try {
            $this->transition->execute($shipment, ShipmentStatus::ASSIGNED, $this->makeDispatcher()->id);
            $this->fail('Expected InvalidShipmentTransitionException');
        } catch (InvalidShipmentTransitionException) {
            // expected
        }

        $this->assertSame(ShipmentStatus::DRAFT, $shipment->fresh()->status->name);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_missing_shipment_creates_zero_notifications(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::WAITING_ASSIGNMENT);
        $stale = $shipment->replicate();
        $stale->id = $shipment->id;
        $stale->exists = true;
        $shipment->delete();

        try {
            $this->transition->execute($stale, ShipmentStatus::ASSIGNED, $this->makeDispatcher()->id);
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException) {
            // expected
        }

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_unauthorized_http_transition_creates_zero_notifications(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::WAITING_ASSIGNMENT);

        $this->actingAs($client, 'api')
            ->patchJson("/api/v1/shipments/{$shipment->id}/status", [
                'status_id' => ShipmentStatus::statusId(ShipmentStatus::ASSIGNED),
            ])
            ->assertForbidden();

        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_same_status_transition_creates_zero_notifications(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::ASSIGNED);

        try {
            $this->transition->execute($shipment, ShipmentStatus::ASSIGNED, $this->makeDispatcher()->id);
            $this->fail('Expected InvalidShipmentTransitionException');
        } catch (InvalidShipmentTransitionException) {
            // expected — domain rejects no-op same-status writes
        }

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_one_successful_transition_creates_exactly_one_notification_per_eligible_client(): void
    {
        $company = Company::factory()->create();
        $first = $this->makeClient($company);
        $second = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::WAITING_ASSIGNMENT);

        $this->transition->execute($shipment, ShipmentStatus::ASSIGNED, $this->makeDispatcher()->id);

        $this->assertSame(2, Notification::query()->count());
        $this->assertSame(1, Notification::query()->where('user_id', $first->id)->count());
        $this->assertSame(1, Notification::query()->where('user_id', $second->id)->count());
        $this->assertSame(2, Notification::query()->where('action_id', $shipment->id)->count());
    }

    public function test_create_shipment_action_creates_zero_shipment_status_notifications(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();

        $result = app(CreateShipmentAction::class)->execute([
            'customer_company_id' => $company->id,
            'priority' => 'normal',
            'status_id' => ShipmentStatus::statusId(ShipmentStatus::WAITING_ASSIGNMENT),
            'created_by' => $dispatcher->id,
            'updated_by' => $dispatcher->id,
        ]);

        $this->assertTrue($result->success);
        $this->assertInstanceOf(Shipment::class, $result->data);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_create_assignment_emits_exactly_one_assigned_notification_per_client(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::WAITING_ASSIGNMENT);
        $payload = $this->assignmentPayload($shipment);

        $result = app(CreateAssignmentAction::class)->execute($payload);

        $this->assertTrue($result->success);
        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertSame(1, Notification::query()->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => $shipment->id,
            'type' => 'shipment.status',
        ]);

        $assignment = Assignment::query()->where('shipment_id', $shipment->id)->firstOrFail();
        $notification = Notification::query()->firstOrFail();
        $this->assertNotSame((string) $assignment->id, $notification->action_id);
        $this->assertNotSame((string) $payload['driver_id'], $notification->action_id);
        $this->assertNotSame((string) $payload['vehicle_id'], $notification->action_id);
    }

    public function test_inactive_assignment_does_not_emit_shipment_status_notification(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::WAITING_ASSIGNMENT);

        $result = app(CreateAssignmentAction::class)->execute($this->assignmentPayload($shipment, [
            'is_active' => false,
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(ShipmentStatus::WAITING_ASSIGNMENT, $shipment->fresh()->status->name);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_lifecycle_adapter_emits_exactly_one_notification_per_successful_transition(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::ASSIGNED);
        $adapter = app(ShipmentLifecycleTransitionAdapter::class);

        $adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Departed);

        $this->assertSame(1, Notification::query()->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_PROGRESS,
            'action_id' => $shipment->id,
            'title' => 'Shipment in transit',
        ]);

        $adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::EnRouteDelivery);
        $adapter->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Departed);

        $this->assertSame(1, Notification::query()->count());
    }

    public function test_lifecycle_adapter_rest_stage_creates_zero_notifications(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::IN_TRANSIT);

        app(ShipmentLifecycleTransitionAdapter::class)
            ->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Resting);

        $this->assertSame(ShipmentStatus::IN_TRANSIT, $shipment->fresh()->status->name);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_lifecycle_adapter_unsupported_leap_creates_zero_notifications(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::ASSIGNED);

        app(ShipmentLifecycleTransitionAdapter::class)
            ->applyOperationalStage($shipment->id, ShipmentLifecycleStage::Delivered);

        $this->assertSame(ShipmentStatus::ASSIGNED, $shipment->fresh()->status->name);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_notification_contract_stores_only_shipment_uuid_and_allowlisted_kind(): void
    {
        $company = Company::factory()->create();
        $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::IN_TRANSIT);

        $this->transition->execute($shipment, ShipmentStatus::DELIVERED, $this->makeDispatcher()->id);

        $row = Notification::query()->firstOrFail();
        $this->assertSame('shipment.status', $row->type);
        $this->assertContains($row->action_kind, ClientNotificationAction::ALLOWED_KINDS);
        $this->assertSame($shipment->id, $row->action_id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $row->action_id
        );
        $this->assertArrayNotHasKey('assignment_id', $row->getAttributes());
        $this->assertArrayNotHasKey('driver_id', $row->getAttributes());
        $this->assertArrayNotHasKey('vehicle_id', $row->getAttributes());
        $this->assertArrayNotHasKey('engine_id', $row->getAttributes());
    }

    private function makeDispatcher(): User
    {
        return User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeCompanyUser(Company $company, Role $role): User
    {
        return User::factory()->create([
            'role_id' => $role->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeShipment(Company $company, string $statusName): Shipment
    {
        return Shipment::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => ShipmentStatus::statusId($statusName),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function assignmentPayload(Shipment $shipment, array $overrides = []): array
    {
        $assignmentStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );

        return array_merge([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'A23-'.fake()->unique()->numerify('#####'),
            'driver_id' => Driver::factory()->create()->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => null,
            'assigned_by' => User::factory()->create(['role_id' => $this->adminRole->id])->id,
            'status_id' => $assignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
            'ended_at' => null,
            'is_active' => true,
            'notes' => 'A2.3 assignment notification test',
        ], $overrides);
    }
}
