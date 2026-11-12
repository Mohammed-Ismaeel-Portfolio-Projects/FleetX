<?php

namespace Tests\Feature\Notification;

use App\Actions\Notification\NotifyCompanyClientUsersAction;
use App\Actions\Notification\WriteClientNotificationAction;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Support\ClientNotificationAction;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * C4.2-A2.1 — Client notification writer infrastructure.
 */
class WriteClientNotificationInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    private WriteClientNotificationAction $writer;

    private NotifyCompanyClientUsersAction $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->writer = app(WriteClientNotificationAction::class);
        $this->notifier = app(NotifyCompanyClientUsersAction::class);
    }

    public function test_valid_allowlisted_kind_and_uuid_persists(): void
    {
        $client = $this->makeClient(Company::factory()->create());
        $actionId = (string) Str::uuid();

        $result = $this->writer->execute($this->writePayload($client->id, [
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => $actionId,
        ]));

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'shipment.status',
            'title' => 'Shipment update',
            'message' => 'Your shipment status has changed.',
            'is_read' => false,
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => $actionId,
        ]);
    }

    public function test_shipment_details_with_shipment_uuid_persists(): void
    {
        $this->assertKindPersists(ClientNotificationAction::KIND_SHIPMENT_DETAILS, 'shipment.status');
    }

    public function test_shipment_progress_with_shipment_uuid_persists(): void
    {
        $this->assertKindPersists(ClientNotificationAction::KIND_SHIPMENT_PROGRESS, 'shipment.status');
    }

    public function test_request_details_with_request_uuid_persists(): void
    {
        $this->assertKindPersists(ClientNotificationAction::KIND_REQUEST_DETAILS, 'shipment.status');
    }

    public function test_conversation_with_conversation_uuid_persists(): void
    {
        $this->assertKindPersists(ClientNotificationAction::KIND_CONVERSATION, 'message.new');
    }

    public function test_unknown_action_kind_is_rejected(): void
    {
        $client = $this->makeClient(Company::factory()->create());

        $result = $this->writer->execute($this->writePayload($client->id, [
            'action_kind' => 'assignment',
            'action_id' => (string) Str::uuid(),
        ]));

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertArrayHasKey('action_kind', (array) $result->errors);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_non_uuid_action_id_is_rejected(): void
    {
        $client = $this->makeClient(Company::factory()->create());

        $result = $this->writer->execute($this->writePayload($client->id, [
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => 'FX-1024',
        ]));

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertArrayHasKey('action_id', (array) $result->errors);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_empty_action_id_is_rejected(): void
    {
        $client = $this->makeClient(Company::factory()->create());

        $result = $this->writer->execute($this->writePayload($client->id, [
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => '   ',
        ]));

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertArrayHasKey('action_id', (array) $result->errors);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $result = $this->writer->execute([]);

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_dispatcher_admin_and_driver_recipients_are_rejected_by_writer(): void
    {
        $company = Company::factory()->create();

        foreach ([$this->adminRole, $this->dispatcherRole, $this->driverRole] as $role) {
            $user = User::factory()->create([
                'role_id' => $role->id,
                'company_id' => $company->id,
                'password' => Hash::make('password'),
            ]);

            $result = $this->writer->execute($this->writePayload($user->id, [
                'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
                'action_id' => (string) Str::uuid(),
            ]));

            $this->assertFalse($result->success, $role->name);
            $this->assertSame(422, $result->statusCode);
        }

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_company_resolution_includes_client_users(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $actionId = (string) Str::uuid();

        $result = $this->notifier->execute($company->id, $this->fanoutPayload([
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => $actionId,
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['written_count']);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => $actionId,
        ]);
    }

    public function test_company_resolution_excludes_dispatcher_and_admin_users(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $result = $this->notifier->execute($company->id, $this->fanoutPayload([
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => (string) Str::uuid(),
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['written_count']);
        $this->assertSame(1, Notification::query()->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $dispatcher->id]);
    }

    public function test_company_resolution_excludes_driver_users(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $driver = User::factory()->create([
            'role_id' => $this->driverRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $result = $this->notifier->execute($company->id, $this->fanoutPayload([
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_PROGRESS,
            'action_id' => (string) Str::uuid(),
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['written_count']);
        $this->assertDatabaseHas('notifications', ['user_id' => $client->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $driver->id]);
    }

    public function test_users_from_another_company_are_never_notified(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $clientB = $this->makeClient($companyB);

        $result = $this->notifier->execute($companyA->id, $this->fanoutPayload([
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => (string) Str::uuid(),
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['written_count']);
        $this->assertDatabaseHas('notifications', ['user_id' => $clientA->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $clientB->id]);
    }

    public function test_company_with_zero_client_users_does_not_fail(): void
    {
        $company = Company::factory()->create();
        User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $result = $this->notifier->execute($company->id, $this->fanoutPayload([
            'action_kind' => ClientNotificationAction::KIND_CONVERSATION,
            'action_id' => (string) Str::uuid(),
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->data['written_count']);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_multiple_client_users_in_the_same_company_each_receive_one_notification(): void
    {
        $company = Company::factory()->create();
        $first = $this->makeClient($company);
        $second = $this->makeClient($company);
        $actionId = (string) Str::uuid();

        $result = $this->notifier->execute($company->id, $this->fanoutPayload([
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => $actionId,
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->data['written_count']);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $first->id,
            'action_id' => $actionId,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $second->id,
            'action_id' => $actionId,
        ]);
        $this->assertSame(2, Notification::query()->count());
    }

    public function test_fanout_ignores_client_supplied_recipient_user_id(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $clientA = $this->makeClient($companyA);
        $clientB = $this->makeClient($companyB);

        $result = $this->notifier->execute($companyA->id, $this->fanoutPayload([
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => (string) Str::uuid(),
            'user_id' => $clientB->id,
            'recipient_user_id' => $clientB->id,
        ]));

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['written_count']);
        $this->assertDatabaseHas('notifications', ['user_id' => $clientA->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $clientB->id]);
    }

    public function test_empty_company_id_is_rejected(): void
    {
        $result = $this->notifier->execute('  ', $this->fanoutPayload([
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => (string) Str::uuid(),
        ]));

        $this->assertFalse($result->success);
        $this->assertSame(422, $result->statusCode);
        $this->assertSame(0, Notification::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function writePayload(int $userId, array $overrides): array
    {
        return array_merge([
            'user_id' => $userId,
            'title' => 'Shipment update',
            'message' => 'Your shipment status has changed.',
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            'action_id' => (string) Str::uuid(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fanoutPayload(array $overrides): array
    {
        return array_merge([
            'title' => 'Request update',
            'message' => 'FleetX updated your shipment request.',
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => (string) Str::uuid(),
        ], $overrides);
    }

    private function assertKindPersists(string $kind, string $type): void
    {
        $client = $this->makeClient(Company::factory()->create());
        $actionId = (string) Str::uuid();

        $result = $this->writer->execute($this->writePayload($client->id, [
            'type' => $type,
            'action_kind' => $kind,
            'action_id' => $actionId,
        ]));

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => $type,
            'action_kind' => $kind,
            'action_id' => $actionId,
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
}
