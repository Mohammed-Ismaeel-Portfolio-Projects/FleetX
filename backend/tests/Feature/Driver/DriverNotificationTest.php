<?php

namespace Tests\Feature\Driver;

use App\Actions\Assignment\ChangeAssignmentStatusAction;
use App\Actions\Assignment\CreateAssignmentAction;
use App\Actions\Conversation\SendMessageAction;
use App\Models\Assignment;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverNotificationCode;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D8 — Driver operational notifications.
 */
class DriverNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $assignedStatus;

    private Status $cancelledStatus;

    private Status $waitingShipmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->assignedStatus = $this->assignmentStatus('Assigned');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        $this->waitingShipmentStatus = $this->shipmentStatus(ShipmentStatus::WAITING_ASSIGNMENT);
        $this->shipmentStatus(ShipmentStatus::ASSIGNED);
    }

    public function test_own_driver_can_list_notifications(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d8-list@fleetx.test');
        DriverNotification::query()->create($this->notifAttrs($driver->id, 'A'));
        [, $other] = $this->makeLinkedDriver('d8-list-other@fleetx.test');
        DriverNotification::query()->create($this->notifAttrs($other->id, 'B'));

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/notifications')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.title', 'A');
    }

    public function test_other_driver_cannot_read_foreign_notification(): void
    {
        [, $owner] = $this->makeLinkedDriver('d8-own@fleetx.test');
        [$otherUser] = $this->makeLinkedDriver('d8-other@fleetx.test');
        $row = DriverNotification::query()->create($this->notifAttrs($owner->id, 'Secret'));

        $token = $this->loginToken($otherUser->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/notifications/'.$row->id.'/read')
            ->assertNotFound();
    }

    public function test_unlinked_driver_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'd8-unlink@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/notifications')
            ->assertForbidden();
    }

    public function test_client_role_forbidden(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd8-client@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
        ]);
        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/notifications')
            ->assertForbidden();
    }

    public function test_unauthenticated_denied(): void
    {
        $this->getJson('/api/v1/driver/notifications')->assertUnauthorized();
    }

    public function test_mark_read_and_read_all_are_idempotent_and_scoped(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d8-read@fleetx.test');
        $a = DriverNotification::query()->create($this->notifAttrs($driver->id, 'One', 'd1'));
        DriverNotification::query()->create($this->notifAttrs($driver->id, 'Two', 'd2'));
        [, $foreign] = $this->makeLinkedDriver('d8-read-f@fleetx.test');
        $foreignRow = DriverNotification::query()->create($this->notifAttrs($foreign->id, 'Foreign', 'df'));

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/notifications/'.$a->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/notifications/'.$a->id.'/read')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/notifications/read-all')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertNull($foreignRow->fresh()->read_at);
    }

    public function test_assignment_create_writes_assigned_notification(): void
    {
        [, $driver] = $this->makeLinkedDriver('d8-assign@fleetx.test');
        $dispatcher = User::factory()->create();
        $shipment = Shipment::factory()->create(['status_id' => $this->waitingShipmentStatus->id]);
        $vehicle = Vehicle::factory()->create();
        $trailer = Trailer::factory()->create();

        $result = app(CreateAssignmentAction::class)->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASN-D8-1',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $this->assignedStatus->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('driver_notifications', [
            'driver_id' => $driver->id,
            'code' => DriverNotificationCode::ASSIGNMENT_ASSIGNED,
        ]);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_assignment_cancel_writes_critical_notification(): void
    {
        [, $driver] = $this->makeLinkedDriver('d8-cancel@fleetx.test');
        $assignment = $this->makeAssignment($driver);
        $dispatcher = User::factory()->create();

        $result = app(ChangeAssignmentStatusAction::class)->execute(
            $assignment,
            $this->cancelledStatus->id,
            $dispatcher->id,
            now()->toDateTimeString()
        );

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('driver_notifications', [
            'driver_id' => $driver->id,
            'code' => DriverNotificationCode::ASSIGNMENT_CANCELLED,
            'severity' => 'critical',
        ]);
    }

    public function test_dispatcher_driver_message_creates_driver_notification_not_client(): void
    {
        [$driverUser, $driver] = $this->makeLinkedDriver('d8-msg@fleetx.test');
        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $dispatcher = User::factory()->create(['role_id' => $dispatcherRole->id]);

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'status' => Conversation::STATUS_OPEN,
            'subject' => 'Trip help',
            'created_by_user_id' => $dispatcher->id,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $dispatcher->id,
            'participant_role' => ConversationParticipant::ROLE_DISPATCHER,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $driverUser->id,
            'participant_role' => ConversationParticipant::ROLE_DRIVER,
        ]);

        $result = app(SendMessageAction::class)->execute($dispatcher, $conversation, 'Please confirm ETA.');
        $this->assertTrue($result->success);

        $this->assertDatabaseHas('driver_notifications', [
            'driver_id' => $driver->id,
            'code' => DriverNotificationCode::MESSAGE_UNREAD,
        ]);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_driver_id_query_prohibited(): void
    {
        [$user] = $this->makeLinkedDriver('d8-probe@fleetx.test');
        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/notifications?driver_id=99')
            ->assertStatus(422);
    }

    /**
     * @return array<string, mixed>
     */
    private function notifAttrs(int $driverId, string $title, string $dedupe = 'x'): array
    {
        return [
            'driver_id' => $driverId,
            'code' => DriverNotificationCode::ASSIGNMENT_ASSIGNED,
            'title' => $title,
            'body' => 'Body',
            'severity' => 'info',
            'dedupe_key' => $dedupe.'-'.$driverId.'-'.$title,
        ];
    }

    private function makeAssignment(Driver $driver): Assignment
    {
        $shipment = Shipment::factory()->create(['status_id' => $this->waitingShipmentStatus->id]);
        $vehicle = Vehicle::factory()->create();
        $trailer = Trailer::factory()->create();
        $dispatcher = User::factory()->create();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $this->assignedStatus->id,
            'is_active' => true,
        ]);
    }

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
        );
    }

    private function shipmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'shipment'],
            ['name' => $name, 'type' => 'shipment']
        );
    }

    /**
     * @return array{0: User, 1: Driver}
     */
    private function makeLinkedDriver(string $email): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);

        return [$user, Driver::factory()->create(['user_id' => $user->id])];
    }

    private function loginToken(string $email): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ]);
        $login->assertOk();

        return $login->json('data.access_token');
    }
}
