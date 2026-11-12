<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\DriverDevice;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * D12 — Push must not leak into Client inbox or other drivers.
 */
class DriverPushIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        config(['driver_push.enabled' => true]);
    }

    public function test_push_does_not_write_client_notifications(): void
    {
        [, $driver] = $this->makeLinkedDriver('d12-iso-client@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d12isoclient0001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'exp.host/*' => Http::response(['data' => ['status' => 'ok']], 200),
        ]);

        DriverNotificationWriter::notify(
            $driver->id,
            DriverNotificationCode::ASSIGNMENT_ASSIGNED,
            'New assignment',
            'Open assignment details.',
            'info',
            null,
            'assignment',
            '1'
        );

        $this->assertSame(0, Notification::query()->count());
        $this->assertDatabaseHas('driver_notifications', [
            'driver_id' => $driver->id,
            'code' => DriverNotificationCode::ASSIGNMENT_ASSIGNED,
        ]);
    }

    public function test_push_does_not_target_another_drivers_device(): void
    {
        [, $driverA] = $this->makeLinkedDriver('d12-iso-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d12-iso-b@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driverB->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d12isob000000001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        Http::fake();

        DriverNotificationWriter::notify(
            $driverA->id,
            DriverNotificationCode::ASSIGNMENT_REASSIGNED,
            'Assignment reassigned',
            'Open Work.',
            'critical',
            null,
            'work',
            null
        );

        Http::assertNothingSent();
    }

    public function test_client_cannot_register_driver_device(): void
    {
        $role = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd12-iso-client-user@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
        ]);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $login->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$login->json('data.access_token'))
            ->postJson('/api/v1/driver/devices/register', [
                'push_token' => 'ExponentPushToken[d12clienttoken01]',
                'platform' => 'android',
            ])
            ->assertForbidden();

        $this->assertSame(0, DriverDevice::query()->count());
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
}
