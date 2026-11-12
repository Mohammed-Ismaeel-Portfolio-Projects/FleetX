<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D13 — Availability API authorization / isolation.
 */
class DriverAvailabilityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $availableStatus;

    private Status $offlineStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->availableStatus = Status::firstOrCreate(
            ['name' => 'Available', 'type' => 'driver'],
            ['name' => 'Available', 'type' => 'driver']
        );
        $this->offlineStatus = Status::firstOrCreate(
            ['name' => 'Offline', 'type' => 'driver'],
            ['name' => 'Offline', 'type' => 'driver']
        );
    }

    public function test_unauthenticated_denied(): void
    {
        $this->getJson('/api/v1/driver/availability')->assertUnauthorized();
        $this->patchJson('/api/v1/driver/availability', [
            'availability' => 'unavailable',
        ])->assertUnauthorized();
    }

    public function test_unlinked_driver_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'd13-unlinked@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertForbidden();
    }

    /**
     * @dataProvider nonDriverRoles
     */
    public function test_non_driver_roles_forbidden(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd13-role-'.$roleName.'@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
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

    public function test_driver_cannot_mutate_another_drivers_availability(): void
    {
        [$userA] = $this->makeLinkedDriver('d13-idor-a@fleetx.test');
        [$userB, $driverB] = $this->makeLinkedDriver('d13-idor-b@fleetx.test');
        $tokenA = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable');

        $this->assertSame($this->availableStatus->id, $driverB->fresh()->status_id);

        $this->withHeader('Authorization', 'Bearer '.$this->loginToken($userB->email))
            ->getJson('/api/v1/driver/availability')
            ->assertOk()
            ->assertJsonPath('data.availability', 'available');
    }

    public function test_driver_id_query_is_prohibited(): void
    {
        [$userA] = $this->makeLinkedDriver('d13-spoof-q@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d13-spoof-qb@fleetx.test');
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability?driver_id='.$driverB->id)
            ->assertStatus(422);
    }

    public function test_driver_id_body_is_prohibited(): void
    {
        [$userA] = $this->makeLinkedDriver('d13-spoof-b@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d13-spoof-bb@fleetx.test');
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', [
                'availability' => 'unavailable',
                'driver_id' => $driverB->id,
            ])
            ->assertStatus(422);

        $this->assertSame($this->availableStatus->id, $driverB->fresh()->status_id);
    }

    public function test_soft_deleted_driver_forbidden(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-deleted@fleetx.test');
        $driver->delete();
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertForbidden();
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

        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status_id' => $this->availableStatus->id,
            'is_active' => true,
        ]);

        return [$user, $driver];
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
