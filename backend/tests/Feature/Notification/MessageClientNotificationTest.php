<?php

namespace Tests\Feature\Notification;

use App\Actions\Conversation\SendMessageAction;
use App\Actions\Notification\WriteClientNotificationAction;
use App\Core\ApiResult;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Support\ClientNotificationAction;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C4.2-A2.4 — Client messaging notification hook.
 */
class MessageClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    private SendMessageAction $send;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->send = app(SendMessageAction::class);
    }

    public function test_dispatcher_to_client_creates_conversation_notification(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$client], $company);

        $result = $this->send->execute($dispatcher, $conversation, 'Please confirm the pickup window.');

        $this->assertTrue($result->success);
        $this->assertInstanceOf(Message::class, $result->data);
        $this->assertSame(1, Message::query()->count());
        $this->assertSame(1, Notification::query()->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'message.new',
            'action_kind' => ClientNotificationAction::KIND_CONVERSATION,
            'action_id' => $conversation->id,
            'title' => 'New FleetX message',
            'message' => 'You have a new message from FleetX Support.',
        ]);
    }

    public function test_client_to_dispatcher_does_not_notify_the_client_sender(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$client], $company);

        $result = $this->send->execute($client, $conversation, 'Pickup is confirmed.');

        $this->assertTrue($result->success);
        $this->assertSame(1, Message::query()->count());
        $this->assertDatabaseMissing('notifications', ['user_id' => $client->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $dispatcher->id]);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_multiple_client_participants_exclude_sender_and_notify_others_once(): void
    {
        $company = Company::factory()->create();
        $sender = $this->makeClient($company);
        $other = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$sender, $other], $company);

        $result = $this->send->execute($sender, $conversation, 'Sharing an update with the team.');

        $this->assertTrue($result->success);
        $this->assertSame(1, Notification::query()->count());
        $this->assertDatabaseMissing('notifications', ['user_id' => $sender->id]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $other->id,
            'action_kind' => ClientNotificationAction::KIND_CONVERSATION,
            'action_id' => $conversation->id,
        ]);
        $this->assertSame(1, Notification::query()->where('user_id', $other->id)->count());
    }

    public function test_dispatcher_driver_conversation_creates_zero_client_notifications(): void
    {
        $dispatcher = $this->makeDispatcher();
        $driver = $this->makeCompanyUser(Company::factory()->create(), $this->driverRole);
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'status' => Conversation::STATUS_OPEN,
            'created_by' => $dispatcher->id,
        ]);
        $this->addParticipant($conversation, $dispatcher, ConversationParticipant::ROLE_DISPATCHER);
        $this->addParticipant($conversation, $driver, ConversationParticipant::ROLE_DRIVER);

        $result = $this->send->execute($dispatcher, $conversation, 'Confirm ETA please.');

        $this->assertTrue($result->success);
        $this->assertSame(1, Message::query()->count());
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_non_participant_never_receives_notification(): void
    {
        $company = Company::factory()->create();
        $participant = $this->makeClient($company);
        $outsider = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$participant], $company);

        $this->send->execute($dispatcher, $conversation, 'Internal update.');

        $this->assertDatabaseHas('notifications', ['user_id' => $participant->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $outsider->id]);
    }

    public function test_unrelated_company_users_never_receive_notification(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $clientB = $this->makeClient($companyB);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$clientA], $companyA);

        $this->send->execute($dispatcher, $conversation, 'Company A only.');

        $this->assertDatabaseHas('notifications', ['user_id' => $clientA->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $clientB->id]);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_failed_message_persistence_creates_zero_notifications(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$client], $company);

        $empty = $this->send->execute($dispatcher, $conversation, '   ');
        $this->assertFalse($empty->success);
        $this->assertSame(0, Message::query()->count());
        $this->assertSame(0, Notification::query()->count());

        $conversation->update(['status' => Conversation::STATUS_CLOSED]);
        $closed = $this->send->execute($dispatcher, $conversation->fresh(), 'Too late.');
        $this->assertFalse($closed->success);
        $this->assertSame(0, Message::query()->count());
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_notification_persistence_failure_does_not_roll_back_message(): void
    {
        Exceptions::fake();

        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$client], $company);

        $this->mock(WriteClientNotificationAction::class, function ($mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn(ApiResult::error('Failed to persist Client notification', 500));
        });

        $result = app(SendMessageAction::class)->execute($dispatcher, $conversation, 'Must survive inbox failure.');

        $this->assertTrue($result->success);
        $this->assertSame(1, Message::query()->where('body', 'Must survive inbox failure.')->count());
        $this->assertSame(0, Notification::query()->count());
        Exceptions::assertReported(\RuntimeException::class);
    }

    public function test_one_successful_message_creates_one_notification_per_eligible_client(): void
    {
        $company = Company::factory()->create();
        $first = $this->makeClient($company);
        $second = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$first, $second], $company);

        $this->send->execute($dispatcher, $conversation, 'Update for both clients.');

        $this->assertSame(1, Message::query()->count());
        $this->assertSame(2, Notification::query()->count());
        $this->assertSame(1, Notification::query()->where('user_id', $first->id)->count());
        $this->assertSame(1, Notification::query()->where('user_id', $second->id)->count());
    }

    public function test_action_id_is_conversation_uuid_not_message_uuid(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $conversation = $this->makeCustomerConversation($dispatcher, [$client], $company);

        $result = $this->send->execute($dispatcher, $conversation, 'UUID contract check.');

        $message = $result->data;
        $this->assertInstanceOf(Message::class, $message);
        $this->assertNotSame($conversation->id, $message->id);

        $notification = Notification::query()->firstOrFail();
        $this->assertSame($conversation->id, $notification->action_id);
        $this->assertNotSame($message->id, $notification->action_id);
        $this->assertSame(ClientNotificationAction::KIND_CONVERSATION, $notification->action_kind);
        $this->assertSame('message.new', $notification->type);
        $this->assertContains($notification->action_kind, ClientNotificationAction::ALLOWED_KINDS);
        $this->assertStringNotContainsString('Please ignore body routing', (string) $notification->title);
        $this->assertSame('You have a new message from FleetX Support.', $notification->message);
    }

    public function test_dispatcher_and_admin_and_driver_participants_are_not_notified(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $dispatcher = $this->makeDispatcher();
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);
        $driver = $this->makeCompanyUser($company, $this->driverRole);
        $conversation = $this->makeCustomerConversation($dispatcher, [$client], $company);
        $this->addParticipant($conversation, $admin, ConversationParticipant::ROLE_DISPATCHER);
        $this->addParticipant($conversation, $driver, ConversationParticipant::ROLE_DRIVER);

        $this->send->execute($dispatcher, $conversation, 'Ops and client on the same thread.');

        $this->assertDatabaseHas('notifications', ['user_id' => $client->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $dispatcher->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $driver->id]);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_client_cannot_create_a_new_conversation(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = Shipment::factory()->create(['customer_company_id' => $company->id]);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/conversations', [
                'type' => Conversation::TYPE_DISPATCHER_CUSTOMER,
                'shipment_id' => $shipment->id,
            ])
            ->assertNotFound();

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, Notification::query()->count());
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

        $this->addParticipant($conversation, $dispatcher, ConversationParticipant::ROLE_DISPATCHER);

        foreach ($clients as $client) {
            $this->addParticipant($conversation, $client, ConversationParticipant::ROLE_CLIENT);
        }

        return $conversation->fresh(['participants']);
    }

    private function addParticipant(Conversation $conversation, User $user, string $role): void
    {
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'participant_role' => $role,
        ]);
    }
}
