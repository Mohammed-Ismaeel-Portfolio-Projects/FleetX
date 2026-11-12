<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\DriverNotificationCode;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D14 — Speed safety authorization / isolation.
 */
class DriverSpeedSafetyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );
        config([
            'driver_safety.speed.enabled' => true,
            'driver_safety.speed.threshold_kmh' => 110,
            'driver_safety.speed.min_samples' => 3,
            'driver_safety.speed.min_duration_seconds' => 10,
            'driver_safety.speed.max_accuracy_meters' => 50,
        ]);
    }

    public function test_unauthenticated_denied(): void
    {
        $this->postJson('/api/v1/driver/assignments/1/tracking', [
            'latitude' => 52.52,
            'longitude' => 13.405,
            'speed' => 160,
        ])->assertUnauthorized();
    }

    public function test_unlinked_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'd14-unlinked@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 160,
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
            'email' => 'd14-role-'.$roleName.'@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 160,
            ])
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

    public function test_wrong_assignment_denied(): void
    {
        [$userA] = $this->makeLinkedDriver('d14-asg-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d14-asg-b@fleetx.test');
        $assignmentB = $this->makeInOperation($driverB);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignmentB->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 160,
                'accuracy' => 8,
            ])
            ->assertNotFound();

        $this->assertSame(0, DriverNotification::query()->where('code', DriverNotificationCode::SPEED_EXCESS)->count());
    }

    public function test_driver_cannot_see_another_drivers_speed_alert(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d14-iso-a@fleetx.test');
        [$userB, $driverB] = $this->makeLinkedDriver('d14-iso-b@fleetx.test');
        $assignmentA = $this->makeInOperation($driverA);
        $this->makeInOperation($driverB);
        $tokenA = $this->loginToken($userA->email);

        foreach ([0, 1, 2] as $i) {
            $this->withHeader('Authorization', 'Bearer '.$tokenA)
                ->postJson('/api/v1/driver/assignments/'.$assignmentA->id.'/tracking', [
                    'latitude' => 52.52 + ($i * 0.0004),
                    'longitude' => 13.405,
                    'accuracy' => 8,
                    'speed' => 140,
                    'recorded_at' => now()->subSeconds(12 - ($i * 6))->toIso8601String(),
                ])
                ->assertOk();
        }

        $this->assertSame(1, DriverNotification::query()->where('driver_id', $driverA->id)->count());
        $this->assertSame(0, DriverNotification::query()->where('driver_id', $driverB->id)->count());

        $listB = $this->withHeader('Authorization', 'Bearer '.$this->loginToken($userB->email))
            ->getJson('/api/v1/driver/notifications')
            ->assertOk();
        $this->assertSame([], $listB->json('data.data'));
    }

    public function test_shipment_id_spoofing_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d14-shp@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'shipment_id' => '00000000-0000-0000-0000-000000000099',
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

    private function makeInOperation(Driver $driver): Assignment
    {
        $shipment = Shipment::factory()->create();

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
