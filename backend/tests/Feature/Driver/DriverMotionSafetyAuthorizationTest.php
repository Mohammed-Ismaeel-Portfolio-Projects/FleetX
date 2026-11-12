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
use App\Models\DriverDevice;
use App\Support\DriverExecutionPhase;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * D15-C — Motion safety authorization / isolation.
 */
class DriverMotionSafetyAuthorizationTest extends TestCase
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
            'driver_safety.motion.enabled' => true,
            'driver_safety.motion.min_samples' => 3,
            'driver_safety.motion.min_duration_seconds' => 10,
            'driver_safety.motion.max_accuracy_meters' => 50,
        ]);
    }

    public function test_unauthenticated_denied(): void
    {
        $this->postJson('/api/v1/driver/assignments/1/tracking', [
            'latitude' => 52.52,
            'longitude' => 13.405,
            'speed' => 80,
        ])->assertUnauthorized();
    }

    public function test_unlinked_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'd15-unlinked@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 80,
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
            'email' => 'd15-role-'.$roleName.'@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 80,
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
        [$userA] = $this->makeLinkedDriver('d15-asg-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d15-asg-b@fleetx.test');
        $assignmentB = $this->makeInOperation($driverB);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignmentB->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 80,
                'accuracy' => 8,
            ])
            ->assertNotFound();

        $this->assertSame(0, DriverNotification::query()->where('code', DriverNotificationCode::HARSH_BRAKING)->count());
        $this->assertSame(0, DriverNotification::query()->where('code', DriverNotificationCode::SUDDEN_STOP)->count());
    }

    public function test_driver_cannot_see_another_drivers_motion_alert(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d15-iso-a@fleetx.test');
        [$userB, $driverB] = $this->makeLinkedDriver('d15-iso-b@fleetx.test');
        $assignmentA = $this->makeInOperation($driverA);
        $this->makeInOperation($driverB);
        $tokenA = $this->loginToken($userA->email);
        $end = now();

        foreach ([80.0, 53.0, 26.0] as $i => $speed) {
            $this->withHeader('Authorization', 'Bearer '.$tokenA)
                ->postJson('/api/v1/driver/assignments/'.$assignmentA->id.'/tracking', [
                    'latitude' => 52.52 + ($i * 0.0004),
                    'longitude' => 13.405,
                    'accuracy' => 8,
                    'speed' => $speed,
                    'recorded_at' => $end->copy()->subSeconds(10 - ($i * 5))->toIso8601String(),
                ])
                ->assertOk();
        }

        $this->assertSame(1, DriverNotification::query()->where('driver_id', $driverA->id)->where('code', DriverNotificationCode::HARSH_BRAKING)->count());
        $this->assertSame(0, DriverNotification::query()->where('driver_id', $driverB->id)->count());

        $listB = $this->withHeader('Authorization', 'Bearer '.$this->loginToken($userB->email))
            ->getJson('/api/v1/driver/notifications')
            ->assertOk();
        $this->assertSame([], $listB->json('data.data'));
    }

    public function test_shipment_id_spoofing_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-shp@fleetx.test');
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

    public function test_vehicle_id_spoofing_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d15-veh@fleetx.test');
        $assignment = $this->makeInOperation($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'speed' => 80,
                'vehicle_id' => '00000000-0000-0000-0000-000000000099',
                'routing_class' => 'van',
            ])
            ->assertStatus(422);
    }

    public function test_driver_cannot_mark_read_another_drivers_motion_alert(): void
    {
        [$userA] = $this->makeLinkedDriver('d15e-read-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d15e-read-b@fleetx.test');
        $assignmentB = $this->makeInOperation($driverB);
        $row = $this->writeMotionEpisode($driverB, $assignmentB, DriverNotificationCode::HARSH_BRAKING, 'idor');

        $token = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/notifications/'.$row->id.'/read')
            ->assertNotFound();

        $this->assertNull($row->fresh()->read_at);
    }

    public function test_forged_motion_notification_id_is_not_found(): void
    {
        [$user] = $this->makeLinkedDriver('d15e-forged@fleetx.test');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/notifications/999999999/read')
            ->assertNotFound();
    }

    public function test_mark_all_read_does_not_touch_another_drivers_motion_alert(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d15e-all-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d15e-all-b@fleetx.test');
        $assignmentA = $this->makeInOperation($driverA);
        $assignmentB = $this->makeInOperation($driverB);
        $own = $this->writeMotionEpisode($driverA, $assignmentA, DriverNotificationCode::SUDDEN_STOP, 'own');
        $foreign = $this->writeMotionEpisode($driverB, $assignmentB, DriverNotificationCode::SUDDEN_STOP, 'foreign');

        $token = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/notifications/read-all')
            ->assertOk();

        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_motion_push_does_not_target_another_drivers_device(): void
    {
        config(['driver_push.enabled' => true]);
        [, $driverA] = $this->makeLinkedDriver('d15e-push-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d15e-push-b@fleetx.test');
        $assignmentA = $this->makeInOperation($driverA);

        DriverDevice::query()->create([
            'driver_id' => $driverA->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d15epusha0000001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
        DriverDevice::query()->create([
            'driver_id' => $driverB->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d15epushb0000001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
        ]);

        DriverNotificationWriter::forAssignment(
            $assignmentA,
            DriverNotificationCode::HARSH_BRAKING,
            'Sharp slowdown detected',
            'A sharp slowdown was detected from your reported speed. Ease off when it is safe. This is an operational notice, not a legal finding.',
            'warning',
            DriverNotificationAction::KIND_ACTIVE_TRIP,
            'push-iso'
        );

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($request->url(), 'exp.host')
                && str_contains($body, 'ExponentPushToken[d15epusha0000001]')
                && ! str_contains($body, 'ExponentPushToken[d15epushb0000001]');
        });
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
            'vehicle_id' => Vehicle::factory()->create([
                'routing_class' => Vehicle::ROUTING_CLASS_HEAVY_TRUCK,
            ])->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->inOperationStatus->id,
            'is_active' => true,
            'execution_phase' => DriverExecutionPhase::PICKUP_PENDING,
        ]);
    }

    private function writeMotionEpisode(
        Driver $driver,
        Assignment $assignment,
        string $code,
        string $suffix
    ): DriverNotification {
        return DriverNotification::query()->create([
            'driver_id' => $driver->id,
            'code' => $code,
            'title' => $code === DriverNotificationCode::SUDDEN_STOP
                ? 'Rapid stop detected'
                : 'Sharp slowdown detected',
            'body' => 'Operational notice.',
            'severity' => 'warning',
            'assignment_id' => $assignment->id,
            'shipment_id' => $assignment->shipment_id,
            'action_kind' => DriverNotificationAction::KIND_ACTIVE_TRIP,
            'action_target' => (string) $assignment->id,
            'dedupe_key' => $code.':'.$assignment->id.':'.$suffix,
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
