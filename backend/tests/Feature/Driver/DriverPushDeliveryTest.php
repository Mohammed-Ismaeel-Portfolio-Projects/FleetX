<?php

namespace Tests\Feature\Driver;

use App\Actions\Assignment\CreateAssignmentAction;
use App\Models\Driver;
use App\Models\DriverDevice;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * D12 — Expo delivery isolation from inbox persist and business actions.
 */
class DriverPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $assignedStatus;

    private Status $waitingShipmentStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->assignedStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
        $this->waitingShipmentStatus = Status::firstOrCreate(
            ['name' => ShipmentStatus::WAITING_ASSIGNMENT, 'type' => 'shipment'],
            ['name' => ShipmentStatus::WAITING_ASSIGNMENT, 'type' => 'shipment']
        );
        Status::firstOrCreate(
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment'],
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment']
        );
        config(['driver_push.enabled' => true]);
    }

    public function test_pushable_code_sends_to_active_devices(): void
    {
        [, $driver] = $this->makeLinkedDriver('d12-push-ok@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d12send00000001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'exp.host/*' => Http::response(['data' => ['status' => 'ok', 'id' => 'ticket-1']], 200),
        ]);

        DriverNotificationWriter::notify(
            $driver->id,
            DriverNotificationCode::ASSIGNMENT_ASSIGNED,
            'New assignment',
            'You have been assigned a new trip.',
            'info',
            null,
            'assignment',
            '99'
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'exp.host'));
        $this->assertDatabaseHas('driver_notifications', [
            'driver_id' => $driver->id,
            'code' => DriverNotificationCode::ASSIGNMENT_ASSIGNED,
        ]);
    }

    public function test_self_generated_code_does_not_push(): void
    {
        [, $driver] = $this->makeLinkedDriver('d12-push-skip@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d12skip00000001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        Http::fake();

        DriverNotificationWriter::notify(
            $driver->id,
            DriverNotificationCode::DELIVERY_COMPLETED,
            'Delivery completed',
            'Trip completed successfully.',
            'info',
            null,
            'work',
            null
        );

        Http::assertNothingSent();
        $this->assertDatabaseHas('driver_notifications', [
            'driver_id' => $driver->id,
            'code' => DriverNotificationCode::DELIVERY_COMPLETED,
        ]);
    }

    public function test_expo_failure_does_not_fail_business_action_or_inbox(): void
    {
        [, $driver] = $this->makeLinkedDriver('d12-push-fail@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'ios',
            'push_token' => 'ExponentPushToken[d12fail00000001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'exp.host/*' => Http::response('upstream unavailable', 503),
        ]);

        $dispatcher = User::factory()->create();
        $result = app(CreateAssignmentAction::class)->execute([
            'shipment_id' => Shipment::factory()->create(['status_id' => $this->waitingShipmentStatus->id])->id,
            'assignment_code' => 'ASN-D12-FAIL',
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $this->assignedStatus->id,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $this->assertTrue($result->success);
        $this->assertDatabaseHas('driver_notifications', [
            'driver_id' => $driver->id,
            'code' => DriverNotificationCode::ASSIGNMENT_ASSIGNED,
        ]);
        $this->assertTrue((bool) DriverDevice::query()->first()->is_active);
    }

    public function test_device_not_registered_deactivates_token(): void
    {
        [, $driver] = $this->makeLinkedDriver('d12-push-inv@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d12invalid00001]',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);

        Http::fake([
            'exp.host/*' => Http::response([
                'data' => [
                    'status' => 'error',
                    'message' => 'Device not registered',
                    'details' => ['error' => 'DeviceNotRegistered'],
                ],
            ], 200),
        ]);

        DriverNotificationWriter::notify(
            $driver->id,
            DriverNotificationCode::ASSIGNMENT_CANCELLED,
            'Assignment cancelled',
            'Your assignment was cancelled.',
            'critical',
            null,
            'work',
            null
        );

        $device = DriverDevice::query()->first();
        $this->assertFalse((bool) $device->is_active);
        $this->assertNotNull($device->invalidated_at);
        $this->assertDatabaseHas('driver_notifications', [
            'driver_id' => $driver->id,
            'code' => DriverNotificationCode::ASSIGNMENT_CANCELLED,
        ]);
    }

    public function test_inactive_device_is_not_targeted(): void
    {
        [, $driver] = $this->makeLinkedDriver('d12-push-off@fleetx.test');
        DriverDevice::query()->create([
            'driver_id' => $driver->id,
            'platform' => 'android',
            'push_token' => 'ExponentPushToken[d12off000000001]',
            'is_active' => false,
            'invalidated_at' => now(),
        ]);

        Http::fake();

        DriverNotificationWriter::notify(
            $driver->id,
            DriverNotificationCode::MESSAGE_UNREAD,
            'New dispatcher message',
            'You have a new message.',
            'info',
            null,
            'conversation',
            'abc'
        );

        Http::assertNothingSent();
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
