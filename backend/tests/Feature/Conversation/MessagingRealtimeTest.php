<?php

namespace Tests\Feature\Conversation;

use App\Events\Conversation\MessageCreated;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Driver;
use App\Models\Message;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MessagingRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private Role $dispatcherRole;
    private Role $driverRole;
    private Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\StatusesSeeder::class);

        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
    }

    public function test_participant_may_authorize_conversation_channel(): void
    {
        $ctx = $this->makeDriverShipmentContext();
        $conversation = $this->openDriverConversation($ctx);

        $response = $this->withToken($this->tokenFor($ctx['dispatcher']))
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-conversation.'.$conversation->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertOk();
        $this->assertTrue(
            filled($response->json('auth')) || filled($response->getContent())
        );
    }

    public function test_manager_may_authorize_even_before_joining_as_participant(): void
    {
        $ctx = $this->makeDriverShipmentContext();
        $conversation = $this->openDriverConversation($ctx);

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $ctx['dispatcher']->id)
            ->delete();

        $response = $this->withToken($this->tokenFor($ctx['dispatcher']))
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-conversation.'.$conversation->id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertOk();
    }

    public function test_driver_participant_may_authorize_own_channel(): void
    {
        $ctx = $this->makeDriverShipmentContext();
        $conversation = $this->openDriverConversation($ctx);

        $response = $this->withToken($this->tokenFor($ctx['driverUser']))
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-conversation.'.$conversation->id,
                'socket_id' => '2222.3333',
            ]);

        $response->assertOk();
    }

    public function test_foreign_driver_cannot_authorize_channel(): void
    {
        $ctx = $this->makeDriverShipmentContext();
        $conversation = $this->openDriverConversation($ctx);

        $otherDriverUser = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);

        $response = $this->withToken($this->tokenFor($otherDriverUser))
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-conversation.'.$conversation->id,
                'socket_id' => '4444.5555',
            ]);

        $response->assertForbidden();
    }

    public function test_client_cannot_authorize_driver_conversation_channel(): void
    {
        $ctx = $this->makeDriverShipmentContext();
        $conversation = $this->openDriverConversation($ctx);
        $client = $this->makeClientUser();

        $response = $this->withToken($this->tokenFor($client))
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-conversation.'.$conversation->id,
                'socket_id' => '6666.7777',
            ]);

        $response->assertForbidden();
    }

    public function test_nonexistent_conversation_channel_is_rejected(): void
    {
        $ctx = $this->makeDriverShipmentContext();

        $response = $this->withToken($this->tokenFor($ctx['dispatcher']))
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-conversation.00000000-0000-0000-0000-000000000099',
                'socket_id' => '8888.9999',
            ]);

        $response->assertForbidden();
    }

    public function test_message_created_broadcasts_after_persistence_with_safe_payload(): void
    {
        Event::fake([MessageCreated::class]);

        $ctx = $this->makeDriverShipmentContext();
        $conversation = $this->openDriverConversation($ctx);

        $response = $this->withToken($this->tokenFor($ctx['dispatcher']))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Realtime payload check',
            ]);

        $response->assertCreated();
        $messageId = $response->json('data.id');
        $this->assertTrue(Message::query()->whereKey($messageId)->exists());

        Event::assertDispatched(MessageCreated::class, function (MessageCreated $event) use ($messageId, $conversation) {
            $payload = $event->broadcastWith();
            $channels = $event->broadcastOn();

            $this->assertSame('message.created', $event->broadcastAs());
            $this->assertSame($messageId, $payload['id']);
            $this->assertSame($conversation->id, $payload['conversation_id']);
            $this->assertSame('Realtime payload check', $payload['body']);
            $this->assertArrayHasKey('sender_user_id', $payload);
            $this->assertArrayHasKey('created_at', $payload);
            $this->assertArrayNotHasKey('password', $payload);
            $this->assertFalse(isset($payload['sender']['password']));

            $this->assertCount(1, $channels);
            $this->assertSame('private-conversation.'.$conversation->id, $channels[0]->name);

            return true;
        });
    }

    public function test_failed_validation_does_not_broadcast_message_created(): void
    {
        Event::fake([MessageCreated::class]);

        $ctx = $this->makeDriverShipmentContext();
        $conversation = $this->openDriverConversation($ctx);

        $this->withToken($this->tokenFor($ctx['dispatcher']))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => '   ',
            ])
            ->assertStatus(422);

        Event::assertNotDispatched(MessageCreated::class);
    }

    /**
     * M8: Reverb/broadcast outage must not fail REST send — message remains SSOT.
     */
    public function test_send_succeeds_when_message_created_broadcast_throws(): void
    {
        Event::listen(MessageCreated::class, function () {
            throw new \RuntimeException('simulated reverb unavailable');
        });

        $ctx = $this->makeDriverShipmentContext();
        $conversation = $this->openDriverConversation($ctx);

        $response = $this->withToken($this->tokenFor($ctx['dispatcher']))
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Survives broadcast failure',
            ]);

        $response->assertCreated();
        $messageId = $response->json('data.id');
        $this->assertTrue(Message::query()->whereKey($messageId)->exists());
        $this->assertSame('Survives broadcast failure', $response->json('data.body'));
    }

    /**
     * @return array{dispatcher:User,driverUser:User,driver:Driver,shipment:Shipment,assignment:Assignment}
     */
    private function makeDriverShipmentContext(?User $dispatcher = null): array
    {
        $dispatcher ??= User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $driverUser = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'password' => Hash::make('password'),
        ]);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
        $company = $this->makeCompany();
        $shipment = Shipment::factory()->create(['customer_company_id' => $company->id]);

        $assignmentStatus = Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );

        $assignment = Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'is_active' => true,
            'status_id' => $assignmentStatus->id,
            'assigned_by' => $dispatcher->id,
            'vehicle_id' => null,
            'trailer_id' => null,
        ]);

        return compact('dispatcher', 'driverUser', 'driver', 'shipment', 'assignment');
    }

    private function openDriverConversation(array $ctx): Conversation
    {
        $open = $this->withToken($this->tokenFor($ctx['dispatcher']))->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $ctx['shipment']->id,
            'driver_user_id' => $ctx['driverUser']->id,
        ]);
        $open->assertSuccessful();

        return Conversation::query()->findOrFail($open->json('data.id'));
    }

    private function makeClientUser(): User
    {
        $company = $this->makeCompany();

        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeCompany(): Company
    {
        return Company::withoutEvents(function () {
            return Company::factory()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'created_by' => null,
                'updated_by' => null,
            ]);
        });
    }

    private function tokenFor(User $user): string
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $login->assertOk();

        return $login->json('data.access_token');
    }
}
