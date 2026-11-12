<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\DriverDevice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D12 — Device register / rotate / unregister.
 */
class DriverDeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
    }

    public function test_linked_driver_can_register_device(): void
    {
        [$user] = $this->makeLinkedDriver('d12-reg@fleetx.test');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12regtoken0001]',
                'platform' => 'android',
                'device_id' => 'install-1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.push_token')
            ->assertJsonMissingPath('data.driver_id');

        $this->assertDatabaseHas('driver_devices', [
            'push_token' => 'ExponentPushToken[d12regtoken0001]',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_registration_is_idempotent(): void
    {
        [$user] = $this->makeLinkedDriver('d12-dup@fleetx.test');
        $token = $this->loginToken($user->email);
        $payload = [
            'push_token' => 'ExponentPushToken[d12duptoken0001]',
            'platform' => 'ios',
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/devices/register', $payload)
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/devices/register', $payload)
            ->assertOk();

        $this->assertSame(1, DriverDevice::query()->count());
    }

    public function test_token_rotation_keeps_one_row_per_token(): void
    {
        [$user] = $this->makeLinkedDriver('d12-rot@fleetx.test');
        $auth = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$auth)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12oldtoken0001]',
                'platform' => 'android',
            ])
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$auth)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12newtoken0001]',
                'platform' => 'android',
            ])
            ->assertOk();

        $this->assertSame(2, DriverDevice::query()->count());
    }

    public function test_token_transfers_when_another_driver_registers_same_device(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d12-xfer-a@fleetx.test');
        [$userB, $driverB] = $this->makeLinkedDriver('d12-xfer-b@fleetx.test');
        $push = 'ExponentPushToken[d12xfertoken0001]';

        $this->withHeader('Authorization', 'Bearer '.$this->loginToken($userA->email))
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => $push,
                'platform' => 'android',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$this->loginToken($userB->email))
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => $push,
                'platform' => 'android',
            ])
            ->assertOk();

        $this->assertSame(1, DriverDevice::query()->count());
        $this->assertSame($driverB->id, DriverDevice::query()->first()->driver_id);
        $this->assertNotSame($driverA->id, DriverDevice::query()->first()->driver_id);
    }

    public function test_unregister_is_idempotent(): void
    {
        [$user] = $this->makeLinkedDriver('d12-unreg@fleetx.test');
        $auth = $this->loginToken($user->email);
        $push = 'ExponentPushToken[d12unregtoken001]';

        $this->withHeader('Authorization', 'Bearer '.$auth)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => $push,
                'platform' => 'ios',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$auth)
            ->postJson('/api/v1/driver/devices/unregister', ['push_token' => $push])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->withHeader('Authorization', 'Bearer '.$auth)
            ->postJson('/api/v1/driver/devices/unregister', ['push_token' => $push])
            ->assertOk();

        $this->assertFalse((bool) DriverDevice::query()->first()->is_active);
    }

    public function test_multiple_active_devices(): void
    {
        [$user] = $this->makeLinkedDriver('d12-multi@fleetx.test');
        $auth = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$auth)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12phone0000001]',
                'platform' => 'android',
            ])
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$auth)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12tablet000001]',
                'platform' => 'ios',
            ])
            ->assertOk();

        $list = $this->withHeader('Authorization', 'Bearer '.$auth)
            ->getJson('/api/v1/driver/devices')
            ->assertOk();
        $this->assertCount(2, $list->json('data'));
        $this->assertArrayNotHasKey('push_token', $list->json('data.0'));
    }

    public function test_driver_id_body_is_prohibited(): void
    {
        [$user] = $this->makeLinkedDriver('d12-spoof@fleetx.test');
        $auth = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$auth)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12spoof0000001]',
                'platform' => 'android',
                'driver_id' => 1,
            ])
            ->assertStatus(422);
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
