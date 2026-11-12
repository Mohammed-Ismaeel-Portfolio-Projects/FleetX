<?php

namespace Tests\Feature\Tracking;

use App\Events\Tracking\TrackingStateBroadcasted;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * INT-004 / INT-005 — ops tracking channel authorization and REST fallback.
 *
 * Clients keep GET /client/shipments/{id}/tracking and must not gain
 * tracking.fleet or other ops broadcast channels.
 */
class TrackingChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    private Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
    }

    public function test_phpunit_uses_log_broadcast_connection(): void
    {
        $this->assertSame('log', config('broadcasting.default'));
    }

    public function test_admin_may_authorize_tracking_fleet(): void
    {
        $this->assertChannelAuthorized($this->makeUser($this->adminRole), 'private-tracking.fleet');
    }

    public function test_dispatcher_may_authorize_tracking_fleet(): void
    {
        $this->assertChannelAuthorized($this->makeUser($this->dispatcherRole), 'private-tracking.fleet');
    }

    public function test_driver_cannot_authorize_tracking_fleet(): void
    {
        $this->assertChannelForbidden($this->makeUser($this->driverRole), 'private-tracking.fleet');
    }

    public function test_client_cannot_authorize_tracking_fleet(): void
    {
        $company = $this->makeCompany();
        $client = $this->makeClient($company);

        $this->assertChannelForbidden($client, 'private-tracking.fleet');
    }

    public function test_dispatcher_may_authorize_existing_vehicle_channel(): void
    {
        $vehicle = Vehicle::factory()->create();
        $dispatcher = $this->makeUser($this->dispatcherRole);

        $this->assertChannelAuthorized($dispatcher, 'private-tracking.vehicle.'.$vehicle->id);
    }

    public function test_admin_may_authorize_existing_vehicle_channel(): void
    {
        $vehicle = Vehicle::factory()->create();
        $admin = $this->makeUser($this->adminRole);

        $this->assertChannelAuthorized($admin, 'private-tracking.vehicle.'.$vehicle->id);
    }

    public function test_driver_cannot_authorize_existing_vehicle_channel(): void
    {
        $vehicle = Vehicle::factory()->create();
        $driver = $this->makeUser($this->driverRole);

        $this->assertChannelForbidden($driver, 'private-tracking.vehicle.'.$vehicle->id);
    }

    public function test_client_cannot_authorize_existing_vehicle_channel(): void
    {
        $vehicle = Vehicle::factory()->create();
        $client = $this->makeClient($this->makeCompany());

        $this->assertChannelForbidden($client, 'private-tracking.vehicle.'.$vehicle->id);
    }

    public function test_missing_vehicle_channel_is_denied_even_for_dispatcher(): void
    {
        $dispatcher = $this->makeUser($this->dispatcherRole);

        $this->assertChannelForbidden(
            $dispatcher,
            'private-tracking.vehicle.'.(string) Str::uuid()
        );
    }

    public function test_dispatcher_may_authorize_existing_shipment_channel(): void
    {
        $shipment = $this->makeShipment($this->makeCompany());
        $dispatcher = $this->makeUser($this->dispatcherRole);

        $this->assertChannelAuthorized($dispatcher, 'private-tracking.shipment.'.$shipment->id);
    }

    public function test_client_cannot_authorize_ops_shipment_channel_even_when_owning_the_shipment(): void
    {
        $company = $this->makeCompany();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->withToken($this->tokenFor($client))
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/tracking')
            ->assertOk();

        $this->assertChannelForbidden($client, 'private-tracking.shipment.'.$shipment->id);
        $this->assertChannelForbidden($client, 'private-tracking.fleet');
    }

    public function test_foreign_client_cannot_authorize_ops_shipment_channel(): void
    {
        $ownerCompany = $this->makeCompany();
        $foreignClient = $this->makeClient($this->makeCompany());
        $shipment = $this->makeShipment($ownerCompany);

        $this->assertChannelForbidden($foreignClient, 'private-tracking.shipment.'.$shipment->id);
    }

    public function test_driver_cannot_authorize_ops_shipment_channel(): void
    {
        $shipment = $this->makeShipment($this->makeCompany());
        $driver = $this->makeUser($this->driverRole);

        $this->assertChannelForbidden($driver, 'private-tracking.shipment.'.$shipment->id);
    }

    public function test_missing_shipment_channel_is_denied_even_for_dispatcher(): void
    {
        $dispatcher = $this->makeUser($this->dispatcherRole);

        $this->assertChannelForbidden(
            $dispatcher,
            'private-tracking.shipment.'.(string) Str::uuid()
        );
    }

    public function test_conversation_channel_authorization_still_allows_dispatcher_manager(): void
    {
        $dispatcher = $this->makeUser($this->dispatcherRole);
        $shipment = $this->makeShipment($this->makeCompany());
        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_DISPATCHER_DRIVER,
            'shipment_id' => $shipment->id,
            'created_by' => $dispatcher->id,
        ]);

        $this->assertChannelAuthorized($dispatcher, 'private-conversation.'.$conversation->id);
    }

    public function test_tracking_state_broadcasted_uses_private_echo_channel_names(): void
    {
        $vehicleId = (string) Str::uuid();
        $shipmentId = (string) Str::uuid();
        $assignmentId = (string) Str::uuid();

        $event = new TrackingStateBroadcasted(
            $vehicleId,
            $shipmentId,
            $assignmentId,
            52.52,
            13.405,
            180.0,
            60.5,
            'Moving',
            'Online',
            5.0,
            1,
            now()->toIso8601String()
        );

        $channelNames = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

        $this->assertSame('tracking.state.updated', $event->broadcastAs());
        $this->assertTrue(
            (new \ReflectionClass(TrackingStateBroadcasted::class))
                ->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class)
        );
        $this->assertContains('private-tracking.vehicle.'.$vehicleId, $channelNames);
        $this->assertContains('private-tracking.shipment.'.$shipmentId, $channelNames);
        $this->assertContains('private-tracking.fleet', $channelNames);
        $this->assertCount(3, $channelNames);
    }

    public function test_dispatcher_live_fleet_rest_works_when_realtime_uses_log_driver(): void
    {
        $this->assertSame('log', config('broadcasting.default'));

        $dispatcher = $this->makeUser($this->dispatcherRole);

        $this->withToken($this->tokenFor($dispatcher))
            ->getJson('/api/v1/tracking/live-fleet')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'vehicles',
                    'meta' => ['count', 'generated_at'],
                ],
            ]);
    }

    public function test_client_cannot_use_live_fleet_rest(): void
    {
        $client = $this->makeClient($this->makeCompany());

        $this->withToken($this->tokenFor($client))
            ->getJson('/api/v1/tracking/live-fleet')
            ->assertForbidden();
    }

    private function assertChannelAuthorized(User $user, string $channelName): void
    {
        $response = $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => $channelName,
                'socket_id' => '1234.5678',
            ]);

        $response->assertOk();
        $this->assertTrue(
            filled($response->json('auth')) || filled($response->getContent())
        );
    }

    private function assertChannelForbidden(User $user, string $channelName): void
    {
        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => $channelName,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    private function makeUser(Role $role): User
    {
        return User::factory()->create([
            'role_id' => $role->id,
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

    private function makeCompany(): Company
    {
        return Company::factory()->create();
    }

    private function makeShipment(Company $company): Shipment
    {
        $status = \App\Models\Status::firstOrCreate(
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE]
        );

        return Shipment::factory()->create([
            'customer_company_id' => $company->id,
            'status_id' => $status->id,
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'pickup_company' => 'Pickup Co',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
            'delivery_company' => 'Delivery Co',
        ]);
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
