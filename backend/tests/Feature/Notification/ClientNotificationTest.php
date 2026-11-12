<?php

namespace Tests\Feature\Notification;

use App\Models\Company;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * C2.8-C security / contract matrix for /api/v1/client/notifications*.
 */
class ClientNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
    }

    public function test_unauthenticated_list_is_rejected(): void
    {
        $this->getJson('/api/v1/client/notifications')->assertUnauthorized();
    }

    public function test_unauthenticated_unread_count_is_rejected(): void
    {
        $this->getJson('/api/v1/client/notifications/unread-count')->assertUnauthorized();
    }

    public function test_linked_client_can_list_own_notifications(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $own = Notification::factory()->create([
            'user_id' => $client->id,
            'type' => 'message.new',
            'title' => 'Own title',
            'message' => 'Own message',
            'is_read' => false,
            'created_at' => now()->subMinute(),
        ]);
        $foreignUser = $this->makeClient(Company::factory()->create());
        Notification::factory()->create([
            'user_id' => $foreignUser->id,
            'title' => 'FOREIGN-SECRET',
            'type' => 'shipment.status',
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $own->id)
            ->assertJsonPath('data.data.0.type', 'message.new')
            ->assertJsonPath('data.data.0.title', 'Own title')
            ->assertJsonPath('data.data.0.message', 'Own message')
            ->assertJsonPath('data.data.0.is_read', false)
            ->assertJsonPath('data.data.0.action', null);

        $this->assertStringNotContainsString('FOREIGN-SECRET', $response->getContent());
        $this->assertResourcePrivacy($response->json('data.data.0'));
    }

    public function test_list_orders_newest_first_deterministically(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $older = Notification::factory()->create([
            'user_id' => $client->id,
            'title' => 'Older',
            'created_at' => now()->subHours(2),
        ]);
        $newer = Notification::factory()->create([
            'user_id' => $client->id,
            'title' => 'Newer',
            'created_at' => now()->subHour(),
        ]);
        // Same created_at — higher id wins as secondary sort
        $sameTimeA = Notification::factory()->create([
            'user_id' => $client->id,
            'title' => 'SameA',
            'created_at' => now(),
        ]);
        $sameTimeB = Notification::factory()->create([
            'user_id' => $client->id,
            'title' => 'SameB',
            'created_at' => $sameTimeA->created_at,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications');

        $response->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertSame(
            [$sameTimeB->id, $sameTimeA->id, $newer->id, $older->id],
            $ids
        );
    }

    public function test_list_default_and_max_page_size(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        Notification::factory()->count(16)->create(['user_id' => $client->id]);

        $default = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications');
        $default->assertOk()
            ->assertJsonPath('data.meta.per_page', 15)
            ->assertJsonPath('data.meta.total', 16);
        $this->assertCount(15, $default->json('data.data'));

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications?per_page=101')
            ->assertStatus(422);

        $capped = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications?per_page=100&page=1');
        $capped->assertOk()->assertJsonPath('data.meta.per_page', 100);
    }

    public function test_list_is_read_filter(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $unread = Notification::factory()->unread()->create(['user_id' => $client->id]);
        Notification::factory()->read()->create(['user_id' => $client->id]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications?is_read=0')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $unread->id);
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $client = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertForbidden();
    }

    public function test_admin_is_forbidden(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertForbidden();
    }

    public function test_dispatcher_is_forbidden(): void
    {
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertForbidden();
    }

    public function test_ownership_query_params_are_rejected_with_422(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        foreach (['user_id=1', 'company_id=1', 'customer_company_id=1', 'owner_id=1', 'recipient_id=1'] as $query) {
            $this->actingAs($client, 'api')
                ->getJson('/api/v1/client/notifications?'.$query)
                ->assertStatus(422);
            $this->actingAs($client, 'api')
                ->getJson('/api/v1/client/notifications/unread-count?'.$query)
                ->assertStatus(422);
            $this->actingAs($client, 'api')
                ->postJson('/api/v1/client/notifications/read-all?'.$query)
                ->assertStatus(422);
        }
    }

    public function test_unread_count_is_user_scoped(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $other = $this->makeClient(Company::factory()->create());

        Notification::factory()->count(2)->unread()->create(['user_id' => $client->id]);
        Notification::factory()->read()->create(['user_id' => $client->id]);
        Notification::factory()->count(5)->unread()->create(['user_id' => $other->id]);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_mark_one_read_is_idempotent_and_owned(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $notification = Notification::factory()->unread()->create([
            'user_id' => $client->id,
            'title' => 'Mark me',
        ]);

        $first = $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/notifications/'.$notification->id.'/read');
        $first->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_read', true);
        $this->assertResourcePrivacy($first->json('data'));
        $this->assertTrue($notification->fresh()->is_read);

        $second = $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/notifications/'.$notification->id.'/read');
        $second->assertOk()->assertJsonPath('data.is_read', true);
    }

    public function test_mark_one_foreign_and_unknown_return_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $foreign = Notification::factory()->create([
            'user_id' => $this->makeClient(Company::factory()->create())->id,
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/notifications/'.$foreign->id.'/read')
            ->assertNotFound();
        $this->assertFalse($foreign->fresh()->is_read);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/notifications/999999/read')
            ->assertNotFound();
    }

    public function test_mark_all_read_updates_only_own_unread(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $other = $this->makeClient(Company::factory()->create());

        Notification::factory()->count(3)->unread()->create(['user_id' => $client->id]);
        Notification::factory()->read()->create(['user_id' => $client->id]);
        $foreignUnread = Notification::factory()->unread()->create(['user_id' => $other->id]);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/notifications/read-all');
        $response->assertOk()->assertJsonPath('data.marked_read', 3);

        $this->assertSame(0, Notification::query()->ownedBy($client->id)->where('is_read', false)->count());
        $this->assertFalse($foreignUnread->fresh()->is_read);

        $again = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/notifications/read-all');
        $again->assertOk()->assertJsonPath('data.marked_read', 0);
    }

    public function test_valid_action_is_projected_without_internal_fields(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipmentId = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';

        $notification = Notification::factory()->create([
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'title' => 'Shipment FX-1024 has been delivered.',
            'message' => 'Your shipment is complete.',
            'action_kind' => 'shipment_details',
            'action_id' => $shipmentId,
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications');

        $response->assertOk()
            ->assertJsonPath('data.data.0.id', $notification->id)
            ->assertJsonPath('data.data.0.action.kind', 'shipment_details')
            ->assertJsonPath('data.data.0.action.id', $shipmentId);

        $item = $response->json('data.data.0');
        $this->assertResourcePrivacy($item);
        $this->assertArrayNotHasKey('action_kind', $item);
        $this->assertArrayNotHasKey('action_id', $item);
        $this->assertArrayNotHasKey('shipment_id', $item['action']);
        $this->assertArrayNotHasKey('assignment_id', $item);
        $this->assertArrayNotHasKey('driver_id', $item);
    }

    public function test_request_and_conversation_actions_are_projected(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $requestId = '11111111-2222-4333-8444-555555555555';
        $conversationId = '66666666-7777-4888-8999-aaaaaaaaaaaa';

        Notification::factory()->create([
            'user_id' => $client->id,
            'type' => 'system.notice',
            'title' => 'Your shipment request has been approved.',
            'action_kind' => 'request_details',
            'action_id' => $requestId,
            'created_at' => now()->subMinute(),
        ]);
        Notification::factory()->create([
            'user_id' => $client->id,
            'type' => 'message.new',
            'title' => 'You have a new message from the dispatcher.',
            'action_kind' => 'conversation',
            'action_id' => $conversationId,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications');

        $response->assertOk();
        $rows = collect($response->json('data.data'));
        $this->assertSame('conversation', $rows[0]['action']['kind']);
        $this->assertSame($conversationId, $rows[0]['action']['id']);
        $this->assertSame('request_details', $rows[1]['action']['kind']);
        $this->assertSame($requestId, $rows[1]['action']['id']);
    }

    public function test_unknown_kind_and_missing_id_project_as_null_action(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        Notification::factory()->create([
            'user_id' => $client->id,
            'title' => 'Unknown kind',
            'action_kind' => 'assignment',
            'action_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            'created_at' => now()->subMinutes(2),
        ]);
        Notification::factory()->create([
            'user_id' => $client->id,
            'title' => 'Missing id',
            'action_kind' => 'shipment_details',
            'action_id' => null,
            'created_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications');

        $response->assertOk();
        foreach ($response->json('data.data') as $item) {
            $this->assertNull($item['action']);
            $this->assertResourcePrivacy($item);
        }
    }

    public function test_mark_read_preserves_projected_action(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $conversationId = 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff';
        $notification = Notification::factory()->unread()->create([
            'user_id' => $client->id,
            'type' => 'message.new',
            'action_kind' => 'conversation',
            'action_id' => $conversationId,
        ]);

        $this->actingAs($client, 'api')
            ->patchJson('/api/v1/client/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.action.kind', 'conversation')
            ->assertJsonPath('data.action.id', $conversationId);
    }

    public function test_empty_inbox_is_valid_success(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonPath('data.data', []);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function assertResourcePrivacy(array $item): void
    {
        foreach ([
            'user_id',
            'company_id',
            'customer_company_id',
            'driver',
            'vehicle',
            'latitude',
            'longitude',
            'gps',
            'updated_at',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $item, $forbidden);
        }

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('message', $item);
        $this->assertArrayHasKey('is_read', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('action', $item);
    }
}
