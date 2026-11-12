<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\DriverDevice;
use App\Models\Role;
use App\Models\User;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * D12 — Push payload is presentation/navigation only.
 */
class DriverPushDeepLinkTest extends TestCase
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

    public function test_payload_contains_only_allowlisted_navigation_fields(): void
    {
        [, $driver] = $this->makeLinkedDriver('d12-dl@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'ios',
            'push_token' => 'ExponentPushToken[d12deeplink0001]',
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
            'Open assignment details to review.',
            'info',
            null,
            DriverNotificationAction::KIND_ASSIGNMENT,
            '1042'
        );

        Http::assertSent(function ($request) {
            $body = $request->data();
            $message = is_array($body[0] ?? null) ? $body[0] : $body;
            $data = $message['data'] ?? [];
            $this->assertSame('driver_notification', $data['type'] ?? null);
            $this->assertArrayHasKey('notification_id', $data);
            $this->assertSame(DriverNotificationCode::ASSIGNMENT_ASSIGNED, $data['code'] ?? null);
            $this->assertSame('assignment', $data['action_kind'] ?? null);
            $this->assertSame('1042', $data['action_target'] ?? null);
            $this->assertArrayNotHasKey('driver_id', $data);
            $this->assertArrayNotHasKey('status', $data);
            $this->assertArrayNotHasKey('execution_phase', $data);
            $this->assertArrayNotHasKey('shipment_status', $data);
            $this->assertArrayNotHasKey('secret', $data);
            $this->assertArrayNotHasKey('access_token', $data);

            return true;
        });
    }

    public function test_cancelled_payload_does_not_encode_assignment_state(): void
    {
        [, $driver] = $this->makeLinkedDriver('d12-dl-cancel@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d12dlcancel0001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'exp.host/*' => Http::response(['data' => ['status' => 'ok']], 200),
        ]);

        DriverNotificationWriter::notify(
            $driver->id,
            DriverNotificationCode::ASSIGNMENT_CANCELLED,
            'Assignment cancelled',
            'Return to Work for next steps.',
            'critical',
            null,
            DriverNotificationAction::KIND_WORK,
            null
        );

        Http::assertSent(function ($request) {
            $body = $request->data();
            $message = is_array($body[0] ?? null) ? $body[0] : $body;
            $data = $message['data'] ?? [];
            $this->assertSame('assignment.cancelled', $data['code'] ?? null);
            $this->assertSame('work', $data['action_kind'] ?? null);
            $this->assertArrayNotHasKey('is_active', $data);
            $this->assertArrayNotHasKey('status', $data);

            return ($message['priority'] ?? null) === 'high';
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
}
