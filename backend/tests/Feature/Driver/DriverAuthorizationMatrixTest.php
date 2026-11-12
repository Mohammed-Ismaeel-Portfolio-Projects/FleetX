<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D10 — Authorization matrix for non-driver roles on Driver work surface.
 */
class DriverAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $clientRole;

    private Status $assignedStatus;

    private Status $inOperationStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->assignedStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
        $this->inOperationStatus = Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );
        Status::firstOrCreate(
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment'],
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment']
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function forbiddenRoleProvider(): array
    {
        return [
            'admin' => ['admin', 'd10-mx-admin@fleetx.test'],
            'dispatcher' => ['dispatcher', 'd10-mx-disp@fleetx.test'],
            'client' => ['client', 'd10-mx-client@fleetx.test'],
        ];
    }

    /**
     * @dataProvider forbiddenRoleProvider
     */
    public function test_non_driver_roles_forbidden_on_driver_work_surface(string $roleName, string $email): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);
        [$driverUser, $driver] = $this->makeLinkedDriver('d10-mx-driver@fleetx.test');
        $assignment = $this->makeAssignment($driver);
        $token = $this->loginToken($user->email);
        $auth = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($auth)->getJson('/api/v1/driver/work/active')->assertForbidden();
        $this->withHeaders($auth)->getJson('/api/v1/driver/assignments/'.$assignment->id)->assertForbidden();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge')->assertForbidden();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')->assertForbidden();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')->assertForbidden();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', ['challenge' => '00000000-0000-0000-0000-000000000001'])->assertForbidden();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
            'latitude' => 1,
            'longitude' => 1,
            'recorded_at' => now()->toIso8601String(),
        ])->assertForbidden();
        $this->withHeaders($auth)->getJson('/api/v1/driver/assignments/'.$assignment->id.'/trip-map')->assertForbidden();
        $this->withHeaders($auth)->getJson('/api/v1/driver/assignments/'.$assignment->id.'/outcome')->assertForbidden();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')->assertForbidden();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/issues', ['type' => 'traffic'])->assertForbidden();
        $this->withHeaders($auth)->getJson('/api/v1/driver/notifications')->assertForbidden();
        $this->withHeaders($auth)->getJson('/api/v1/driver/assignments/history')->assertForbidden();
        $this->withHeaders($auth)->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')->assertForbidden();
        $this->withHeaders($auth)->getJson('/api/v1/driver/devices')->assertForbidden();
        $this->withHeaders($auth)->postJson('/api/v1/driver/devices/register', [
            'push_token' => 'ExponentPushToken[d10mxdevice0001]',
            'platform' => 'android',
        ])->assertForbidden();
        $this->withHeaders($auth)->getJson('/api/v1/driver/availability')->assertForbidden();
        $this->withHeaders($auth)->patchJson('/api/v1/driver/availability', [
            'availability' => 'unavailable',
        ])->assertForbidden();

        // Sanity: linked driver can read own assignment
        $driverToken = $this->loginToken($driverUser->email);
        $this->withHeader('Authorization', 'Bearer '.$driverToken)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id)
            ->assertOk();
    }

    public function test_unauthenticated_denied_on_driver_work_surface(): void
    {
        [, $driver] = $this->makeLinkedDriver('d10-mx-unauth-driver@fleetx.test');
        $assignment = $this->makeAssignment($driver);

        $this->getJson('/api/v1/driver/work/active')->assertUnauthorized();
        $this->getJson('/api/v1/driver/assignments/'.$assignment->id)->assertUnauthorized();
        $this->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge')->assertUnauthorized();
        $this->getJson('/api/v1/driver/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/driver/assignments/history')->assertUnauthorized();
        $this->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')->assertUnauthorized();
        $this->getJson('/api/v1/driver/devices')->assertUnauthorized();
        $this->getJson('/api/v1/driver/availability')->assertUnauthorized();
        $this->patchJson('/api/v1/driver/availability', [
            'availability' => 'unavailable',
        ])->assertUnauthorized();
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

    private function makeAssignment(Driver $driver): Assignment
    {
        $shipmentStatus = Status::where('name', ShipmentStatus::ASSIGNED)->where('type', 'shipment')->firstOrFail();
        $shipment = Shipment::factory()->create(['status_id' => $shipmentStatus->id]);

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->inOperationStatus->id,
            'is_active' => true,
            'execution_phase' => DriverExecutionPhase::PICKUP_PENDING,
        ]);
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
