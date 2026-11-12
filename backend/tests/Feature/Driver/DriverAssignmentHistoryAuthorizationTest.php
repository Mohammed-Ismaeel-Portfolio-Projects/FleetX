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
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D11 — History authorization matrix (roles, unlinked, unauthenticated, IDOR).
 */
class DriverAssignmentHistoryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $completedStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->completedStatus = Status::firstOrCreate(
            ['name' => 'Completed', 'type' => 'assignment'],
            ['name' => 'Completed', 'type' => 'assignment']
        );
    }

    public function test_unauthenticated_denied(): void
    {
        $this->getJson('/api/v1/driver/assignments/history')->assertUnauthorized();
        $this->getJson('/api/v1/driver/assignments/1/summary')->assertUnauthorized();
    }

    public function test_unlinked_driver_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'd11-unlinked@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history')
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/1/summary')
            ->assertForbidden();
    }

    /**
     * @dataProvider nonDriverRoles
     */
    public function test_non_driver_roles_forbidden(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd11-role-'.$roleName.'@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history')
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/1/summary')
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

    public function test_other_driver_summary_is_not_found(): void
    {
        [$userA] = $this->makeLinkedDriver('d11-auth-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d11-auth-b@fleetx.test');
        $other = $this->makeCompleted($driverB);

        $token = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$other->id.'/summary')
            ->assertNotFound();
    }

    public function test_soft_deleted_assignment_summary_is_not_found(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-auth-del@fleetx.test');
        $assignment = $this->makeCompleted($driver);
        $assignment->delete();

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')
            ->assertNotFound();
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

    private function makeCompleted(Driver $driver): Assignment
    {
        $shipment = Shipment::factory()->create();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->completedStatus->id,
            'is_active' => false,
            'execution_phase' => DriverExecutionPhase::POD_SUBMITTED,
            'ended_at' => now(),
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
