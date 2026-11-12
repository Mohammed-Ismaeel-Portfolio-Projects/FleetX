<?php

namespace Tests\Feature\Conversation;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessagingBackendTest extends TestCase
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

    public function test_messaging_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('conversations'));
        $this->assertTrue(Schema::hasTable('conversation_participants'));
        $this->assertTrue(Schema::hasTable('messages'));
    }

    public function test_dispatcher_opens_driver_conversation_sends_and_reads(): void
    {
        $ctx = $this->makeDriverShipmentContext();
        $token = $this->tokenFor($ctx['dispatcher']);

        $open = $this->withToken($token)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $ctx['shipment']->id,
            'driver_user_id' => $ctx['driverUser']->id,
        ]);

        $open->assertCreated();
        $conversationId = $open->json('data.id');
        $this->assertNotEmpty($conversationId);

        // Duplicate open reuses same conversation
        $reopen = $this->withToken($token)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $ctx['shipment']->id,
        ]);
        $reopen->assertOk();
        $this->assertSame($conversationId, $reopen->json('data.id'));

        $send = $this->withToken($token)->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => 'Please confirm ETA',
        ]);
        $send->assertCreated();
        $this->assertSame($ctx['dispatcher']->id, $send->json('data.sender_user_id'));

        $messages = $this->withToken($token)->getJson("/api/v1/conversations/{$conversationId}/messages");
        $messages->assertOk();
        $this->assertGreaterThanOrEqual(1, count($messages->json('data.data')));

        $read = $this->withToken($token)->postJson("/api/v1/conversations/{$conversationId}/read");
        $read->assertOk();
        $this->assertNotNull($read->json('data.last_read_at'));
    }

    public function test_driver_sees_own_thread_and_not_customer_thread(): void
    {
        $driverCtx = $this->makeDriverShipmentContext();
        $customerCtx = $this->makeCustomerShipmentContext($driverCtx['dispatcher']);

        $dispToken = $this->tokenFor($driverCtx['dispatcher']);

        $driverThread = $this->withToken($dispToken)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $driverCtx['shipment']->id,
            'driver_user_id' => $driverCtx['driverUser']->id,
        ])->json('data.id');

        $customerThread = $this->withToken($dispToken)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_CUSTOMER,
            'shipment_id' => $customerCtx['shipment']->id,
            'client_user_id' => $customerCtx['clientUser']->id,
        ])->json('data.id');

        $driverToken = $this->tokenFor($driverCtx['driverUser']);

        $this->withToken($driverToken)->getJson("/api/v1/conversations/{$driverThread}")->assertOk();
        $this->withToken($driverToken)->getJson("/api/v1/conversations/{$customerThread}")->assertForbidden();

        $list = $this->withToken($driverToken)->getJson('/api/v1/conversations');
        $list->assertOk();
        $ids = collect($list->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($driverThread));
        $this->assertFalse($ids->contains($customerThread));
    }

    public function test_client_isolation_from_driver_thread(): void
    {
        $driverCtx = $this->makeDriverShipmentContext();
        $customerCtx = $this->makeCustomerShipmentContext($driverCtx['dispatcher']);
        $dispToken = $this->tokenFor($driverCtx['dispatcher']);

        $driverThread = $this->withToken($dispToken)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $driverCtx['shipment']->id,
            'driver_user_id' => $driverCtx['driverUser']->id,
        ])->json('data.id');

        $customerThread = $this->withToken($dispToken)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_CUSTOMER,
            'shipment_id' => $customerCtx['shipment']->id,
            'client_user_id' => $customerCtx['clientUser']->id,
        ])->json('data.id');

        $clientToken = $this->tokenFor($customerCtx['clientUser']);

        $this->withToken($clientToken)->getJson("/api/v1/conversations/{$customerThread}")->assertOk();
        $this->withToken($clientToken)->getJson("/api/v1/conversations/{$driverThread}")->assertForbidden();
        $this->withToken($clientToken)->postJson("/api/v1/conversations/{$driverThread}/messages", [
            'body' => 'should fail',
        ])->assertForbidden();
    }

    public function test_rejects_empty_message_and_unlinked_driver(): void
    {
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);
        $company = $this->makeCompany();
        $shipment = Shipment::factory()->create(['customer_company_id' => $company->id]);

        $token = $this->tokenFor($dispatcher);

        // No linked driver user / assignment
        $open = $this->withToken($token)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $shipment->id,
        ]);
        $open->assertStatus(422);

        $ctx = $this->makeDriverShipmentContext($dispatcher);
        $conversationId = $this->withToken($token)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $ctx['shipment']->id,
            'driver_user_id' => $ctx['driverUser']->id,
        ])->json('data.id');

        $this->withToken($token)->postJson("/api/v1/conversations/{$conversationId}/messages", [
            'body' => '',
        ])->assertStatus(422);
    }

    public function test_driver_cannot_create_new_thread(): void
    {
        $ctx = $this->makeDriverShipmentContext();
        $driverToken = $this->tokenFor($ctx['driverUser']);

        $this->withToken($driverToken)->postJson('/api/v1/conversations', [
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $ctx['shipment']->id,
        ])->assertNotFound();
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

    /**
     * @return array{clientUser:User,company:Company,shipment:Shipment}
     */
    private function makeCustomerShipmentContext(User $dispatcher): array
    {
        $company = $this->makeCompany();
        $clientUser = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
        $shipment = Shipment::factory()->create(['customer_company_id' => $company->id]);

        return compact('clientUser', 'company', 'shipment');
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
