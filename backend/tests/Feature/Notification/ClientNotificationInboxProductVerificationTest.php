<?php

namespace Tests\Feature\Notification;

use App\Actions\Conversation\SendMessageAction;
use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Actions\ShipmentOrder\ApproveShipmentOrderAction;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ClientNotificationAction;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Database\Seeders\ShipmentOrderStatusesSeeder;
use Database\Seeders\ShipmentStatusesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C4.2-A4.2 — end-to-end product verification:
 * domain mutation → notification row → Client HTTP inbox contract.
 *
 * Does not redesign writers, routes, or projection. Consumes A2 + C2.8-C as-is.
 */
class ClientNotificationInboxProductVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $dispatcherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->seed(ShipmentOrderStatusesSeeder::class);
        $this->seed(ShipmentStatusesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
    }

    public function test_scenario_request_approved_appears_in_inbox_with_request_action_and_unread(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $foreign = $this->makeClient(Company::factory()->create());
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        $this->assertTrue(
            app(ApproveShipmentOrderAction::class)
                ->execute($this->makeDispatcher(), $order->id, 'Approved for conversion')
                ->success
        );

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $list = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications');

        $list->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.type', 'shipment.status')
            ->assertJsonPath('data.data.0.title', 'Shipment request approved')
            ->assertJsonPath('data.data.0.message', 'Your shipment request has been approved by FleetX.')
            ->assertJsonPath('data.data.0.is_read', false)
            ->assertJsonPath('data.data.0.action.kind', ClientNotificationAction::KIND_REQUEST_DETAILS)
            ->assertJsonPath('data.data.0.action.id', $order->id);

        $item = $list->json('data.data.0');
        $this->assertArrayNotHasKey('action_kind', $item);
        $this->assertArrayNotHasKey('action_id', $item);
        $this->assertArrayNotHasKey('user_id', $item);

        $this->actingAs($foreign, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->actingAs($foreign, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_scenario_shipment_in_transit_inbox_maps_shipment_progress_action(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::ASSIGNED);

        app(TransitionShipmentStatusAction::class)
            ->execute($shipment, ShipmentStatus::IN_TRANSIT, $this->makeDispatcher()->id);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Shipment in transit')
            ->assertJsonPath('data.data.0.message', 'Your shipment is now in transit.')
            ->assertJsonPath('data.data.0.type', 'shipment.status')
            ->assertJsonPath('data.data.0.action.kind', ClientNotificationAction::KIND_SHIPMENT_PROGRESS)
            ->assertJsonPath('data.data.0.action.id', $shipment->id);
    }

    public function test_scenario_shipment_delivered_inbox_maps_shipment_progress_action(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company, ShipmentStatus::IN_TRANSIT);

        app(TransitionShipmentStatusAction::class)
            ->execute($shipment, ShipmentStatus::DELIVERED, $this->makeDispatcher()->id);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertOk()
            ->assertJsonPath('data.data.0.title', 'Shipment delivered')
            ->assertJsonPath('data.data.0.message', 'Your shipment has been delivered.')
            ->assertJsonPath('data.data.0.action.kind', ClientNotificationAction::KIND_SHIPMENT_PROGRESS)
            ->assertJsonPath('data.data.0.action.id', $shipment->id);
    }

    public function test_scenario_dispatcher_message_appears_in_inbox_with_conversation_action(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$client], $company);

        $this->assertTrue(
            app(SendMessageAction::class)
                ->execute($dispatcher, $conversation, 'Please confirm the pickup window.')
                ->success
        );

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertOk()
            ->assertJsonPath('data.data.0.type', 'message.new')
            ->assertJsonPath('data.data.0.title', 'New FleetX message')
            ->assertJsonPath('data.data.0.message', 'You have a new message from FleetX Support.')
            ->assertJsonPath('data.data.0.action.kind', ClientNotificationAction::KIND_CONVERSATION)
            ->assertJsonPath('data.data.0.action.id', $conversation->id);
    }

    public function test_scenario_mark_one_read_decrements_unread_without_touching_foreign_rows(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $foreign = $this->makeClient(Company::factory()->create());
        $order = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $company);

        app(ApproveShipmentOrderAction::class)
            ->execute($this->makeDispatcher(), $order->id, 'Approved for conversion');

        $own = Notification::query()->where('user_id', $client->id)->firstOrFail();
        $foreignUnread = Notification::factory()->unread()->create(['user_id' => $foreign->id]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/notifications/'.$own->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $own->id)
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.action.kind', ClientNotificationAction::KIND_REQUEST_DETAILS)
            ->assertJsonPath('data.action.id', $order->id);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertFalse($foreignUnread->fresh()->is_read);
        $this->assertTrue($own->fresh()->is_read);
    }

    public function test_scenario_mark_all_read_zeroes_unread_for_authenticated_client_only(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $foreign = $this->makeClient(Company::factory()->create());
        $shipment = $this->makeShipment($company, ShipmentStatus::ASSIGNED);
        $dispatcher = $this->makeDispatcher();

        app(TransitionShipmentStatusAction::class)
            ->execute($shipment, ShipmentStatus::IN_TRANSIT, $dispatcher->id);
        app(TransitionShipmentStatusAction::class)
            ->execute($shipment->fresh(), ShipmentStatus::DELIVERED, $dispatcher->id);

        $foreignUnread = Notification::factory()->unread()->create(['user_id' => $foreign->id]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked_read', 2);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, Notification::query()->ownedBy($client->id)->where('is_read', false)->count());
        $this->assertFalse($foreignUnread->fresh()->is_read);
    }

    public function test_scenario_cross_company_and_cross_user_isolation_on_product_path(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $clientB = $this->makeClient($companyB);
        $orderA = $this->makeOrderInStatus(ShipmentOrderStatus::UNDER_REVIEW, $companyA);

        app(ApproveShipmentOrderAction::class)
            ->execute($this->makeDispatcher(), $orderA->id, 'Approved for conversion');

        $notificationA = Notification::query()->where('user_id', $clientA->id)->firstOrFail();

        $this->actingAs($clientB, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);

        $this->actingAs($clientB, 'api')
            ->patchJson('/api/v1/client/notifications/'.$notificationA->id.'/read')
            ->assertNotFound();

        $this->assertFalse($notificationA->fresh()->is_read);

        foreach (['user_id=1', 'company_id=1', 'customer_company_id=1', 'owner_id=1', 'recipient_id=1'] as $query) {
            $this->actingAs($clientA, 'api')
                ->getJson('/api/v1/client/notifications?'.$query)
                ->assertStatus(422);
        }
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

    private function makeOrderInStatus(string $statusName, Company $company): ShipmentOrder
    {
        return ShipmentOrder::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => Status::where('name', $statusName)
                ->where('type', ShipmentOrderStatus::TYPE)
                ->firstOrFail()
                ->id,
            'converted_shipment_id' => null,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
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
     * @param  list<User>  $clients
     */
    private function makeCustomerConversation(User $dispatcher, array $clients, Company $company): Conversation
    {
        $shipment = Shipment::factory()->create(['customer_company_id' => $company->id]);

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DISPATCHER_CUSTOMER,
            'shipment_id' => $shipment->id,
            'status' => Conversation::STATUS_OPEN,
            'created_by' => $dispatcher->id,
        ]);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $dispatcher->id,
            'participant_role' => ConversationParticipant::ROLE_DISPATCHER,
        ]);

        foreach ($clients as $client) {
            ConversationParticipant::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $client->id,
                'participant_role' => ConversationParticipant::ROLE_CLIENT,
            ]);
        }

        return $conversation->fresh(['participants']);
    }
}
