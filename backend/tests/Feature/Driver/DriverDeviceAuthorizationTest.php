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
 * D12 — Device API authorization / IDOR.
 */
class DriverDeviceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
    }

    public function test_unauthenticated_denied(): void
    {
        $this->postJson('/api/v1/driver/devices/register', [
            'push_token' => 'ExponentPushToken[d12anon00000001]',
            'platform' => 'android',
        ])->assertUnauthorized();
        $this->postJson('/api/v1/driver/devices/unregister', [
            'push_token' => 'ExponentPushToken[d12anon00000001]',
        ])->assertUnauthorized();
        $this->getJson('/api/v1/driver/devices')->assertUnauthorized();
    }

    public function test_unlinked_driver_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'd12-unlinked@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12unlinked00001]',
                'platform' => 'android',
            ])
            ->assertForbidden();
    }

    /**
     * @dataProvider nonDriverRoles
     */
    public function test_non_driver_roles_forbidden(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd12-role-'.$roleName.'@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12role'.$roleName.'01]',
                'platform' => 'android',
            ])
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/devices')
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonDriverRoles(): array
    {
        return [
            'admin' => ['admin'],
            'dispatcher' => ['dispatcher'],
            'client' => ['client'],
        ];
    }

    public function test_driver_cannot_list_or_unregister_another_drivers_device(): void
    {
        [$userA] = $this->makeLinkedDriver('d12-idor-a@fleetx.test');
        [$userB, $driverB] = $this->makeLinkedDriver('d12-idor-b@fleetx.test');
        $push = 'ExponentPushToken[d12idorbtoken001]';

        DriverDevice::query()->create([
            'driver_id' => $driverB->id,
            'platform' => 'android',
            'push_token' => $push,
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        $tokenA = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/driver/devices')
            ->assertOk();
        $this->assertSame([], $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/driver/devices')
            ->json('data'));

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/driver/devices/unregister', ['push_token' => $push])
            ->assertOk()
            ->assertJsonPath('data.unregistered', false);

        $this->assertTrue((bool) DriverDevice::query()->where('push_token', $push)->first()->is_active);

        $this->withHeader('Authorization', 'Bearer '.$this->loginToken($userB->email))
            ->getJson('/api/v1/driver/devices')
            ->assertOk()
            ->assertJsonPath('data.0.platform', 'android');
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
