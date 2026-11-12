<?php

namespace Tests\Feature\Driver;

use App\Actions\Driver\GetDriverAvailabilityAction;
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
use App\Support\DriverOperationalAvailability;
use App\Support\DriverSelfServiceAvailability;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D13 — Driver self-service availability (Available ↔ Offline).
 */
class DriverAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $availableStatus;

    private Status $offlineStatus;

    private Status $busyStatus;

    private Status $onLeaveStatus;

    private Status $assignedStatus;

    private Status $readyStatus;

    private Status $inOperationStatus;

    private Status $completedStatus;

    private Status $cancelledStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->availableStatus = $this->driverStatus('Available');
        $this->offlineStatus = $this->driverStatus('Offline');
        $this->busyStatus = $this->driverStatus('Busy');
        $this->onLeaveStatus = $this->driverStatus('On Leave');
        $this->driverStatus('Suspended');
        $this->assignedStatus = $this->assignmentStatus('Assigned');
        $this->readyStatus = $this->assignmentStatus('Ready');
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->completedStatus = $this->assignmentStatus('Completed');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        Status::firstOrCreate(
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment'],
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment']
        );
    }

    public function test_get_current_availability_defaults_to_available(): void
    {
        [$user] = $this->makeLinkedDriver('d13-get@fleetx.test');
        $token = $this->loginToken($user->email);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertOk()
            ->assertJsonPath('data.availability', 'available')
            ->assertJsonPath('data.can_change', true)
            ->assertJsonPath('data.reason', null);

        $payload = $response->json('data');
        $this->assertSame(['availability', 'can_change', 'reason'], array_keys($payload));
        $this->assertArrayNotHasKey('status_id', $payload);
        $this->assertArrayNotHasKey('is_active', $payload);
        $this->assertArrayNotHasKey('driver_id', $payload);
    }

    public function test_set_unavailable(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-off@fleetx.test');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable')
            ->assertJsonPath('data.can_change', true)
            ->assertJsonPath('data.reason', null);

        $driver->refresh();
        $this->assertSame($this->offlineStatus->id, $driver->status_id);
        $this->assertTrue((bool) $driver->is_active);
        $this->assertSame(
            'Offline',
            DriverOperationalAvailability::fromStatus($driver->status, false)
        );
        $this->assertSame(0, DriverNotification::query()->where('driver_id', $driver->id)->count());
    }

    public function test_set_available(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-on@fleetx.test', $this->offlineStatus);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'available')
            ->assertJsonPath('data.can_change', true);

        $this->assertSame($this->availableStatus->id, $driver->fresh()->status_id);
    }

    public function test_idempotent_available(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-idem-on@fleetx.test');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'available');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'available');

        $this->assertSame($this->availableStatus->id, $driver->fresh()->status_id);
    }

    public function test_idempotent_unavailable(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-idem-off@fleetx.test', $this->offlineStatus);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable');

        $this->assertSame($this->offlineStatus->id, $driver->fresh()->status_id);
    }

    public function test_repeated_unavailable_request(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-repeat@fleetx.test');
        $token = $this->loginToken($user->email);

        for ($i = 0; $i < 3; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
                ->assertOk()
                ->assertJsonPath('data.availability', 'unavailable');
        }

        $this->assertSame($this->offlineStatus->id, $driver->fresh()->status_id);
    }

    public function test_malformed_availability_rejected(): void
    {
        [$user] = $this->makeLinkedDriver('d13-bad@fleetx.test');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'busy'])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'Available'])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', [])
            ->assertStatus(422);
    }

    public function test_in_operation_cannot_become_unavailable(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-trip@fleetx.test');
        $this->makeAssignment($driver, $this->inOperationStatus, true, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertOk()
            ->assertJsonPath('data.availability', 'available')
            ->assertJsonPath('data.can_change', false)
            ->assertJsonPath('data.reason', DriverSelfServiceAvailability::BLOCKED_DURING_TRIP);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', DriverSelfServiceAvailability::BLOCKED_DURING_TRIP);

        $this->assertSame($this->availableStatus->id, $driver->fresh()->status_id);
    }

    public function test_assigned_before_execution_cannot_become_unavailable(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-assigned@fleetx.test');
        $this->makeAssignment($driver, $this->assignedStatus, true, null);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', DriverSelfServiceAvailability::BLOCKED_DURING_TRIP);
    }

    public function test_ready_assignment_cannot_become_unavailable(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-ready@fleetx.test');
        $this->makeAssignment($driver, $this->readyStatus, true, null);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', DriverSelfServiceAvailability::BLOCKED_DURING_TRIP);
    }

    public function test_available_during_trip_is_idempotent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-trip-on@fleetx.test');
        $this->makeAssignment($driver, $this->inOperationStatus, true, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'available')
            ->assertJsonPath('data.can_change', false)
            ->assertJsonPath('data.reason', DriverSelfServiceAvailability::BLOCKED_DURING_TRIP);

        $this->assertSame($this->availableStatus->id, $driver->fresh()->status_id);
    }

    public function test_cancelled_historical_assignment_allows_unavailable(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-cancel@fleetx.test');
        $this->makeAssignment($driver, $this->cancelledStatus, false, DriverExecutionPhase::PICKUP_PENDING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable')
            ->assertJsonPath('data.can_change', true);

        $this->assertSame($this->offlineStatus->id, $driver->fresh()->status_id);
    }

    public function test_completed_historical_assignment_allows_unavailable(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-done@fleetx.test');
        $this->makeAssignment($driver, $this->completedStatus, false, DriverExecutionPhase::POD_SUBMITTED);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable');
    }

    public function test_busy_without_assignment_normalizes_to_available(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-busy@fleetx.test', $this->busyStatus);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertOk()
            ->assertJsonPath('data.availability', 'available')
            ->assertJsonPath('data.can_change', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'available'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'available');

        $this->assertSame($this->availableStatus->id, $driver->fresh()->status_id);
    }

    public function test_on_leave_is_locked(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-leave@fleetx.test', $this->onLeaveStatus);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable')
            ->assertJsonPath('data.can_change', false)
            ->assertJsonPath('data.reason', DriverSelfServiceAvailability::LOCKED);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'available'])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', DriverSelfServiceAvailability::LOCKED);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable');

        $this->assertSame($this->onLeaveStatus->id, $driver->fresh()->status_id);
    }

    public function test_inactive_employment_is_locked(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d13-inactive@fleetx.test');
        $driver->update(['is_active' => false]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/availability')
            ->assertOk()
            ->assertJsonPath('data.availability', 'unavailable')
            ->assertJsonPath('data.can_change', false)
            ->assertJsonPath('data.reason', DriverSelfServiceAvailability::LOCKED);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'available'])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', DriverSelfServiceAvailability::LOCKED);

        $this->assertFalse((bool) $driver->fresh()->is_active);
        $this->assertSame($this->availableStatus->id, $driver->fresh()->status_id);
    }

    public function test_ops_aggregate_reflects_self_service_offline(): void
    {
        [$user] = $this->makeLinkedDriver('d13-ops@fleetx.test');
        $token = $this->loginToken($user->email);

        $before = app(GetDriverAvailabilityAction::class)->execute();
        $this->assertTrue($before->success);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/driver/availability', ['availability' => 'unavailable'])
            ->assertOk();

        $after = app(GetDriverAvailabilityAction::class)->execute();
        $this->assertTrue($after->success);
        $this->assertSame(
            ($before->data['offline'] ?? 0) + 1,
            $after->data['offline']
        );
    }

    /**
     * @return array{0: User, 1: Driver}
     */
    private function makeLinkedDriver(string $email, ?Status $status = null): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);

        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status_id' => ($status ?? $this->availableStatus)->id,
            'is_active' => true,
        ]);

        return [$user, $driver];
    }

    private function makeAssignment(Driver $driver, Status $status, bool $active, ?string $phase): Assignment
    {
        $shipmentStatus = Status::where('name', ShipmentStatus::ASSIGNED)->where('type', 'shipment')->firstOrFail();
        $shipment = Shipment::factory()->create(['status_id' => $shipmentStatus->id]);

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $status->id,
            'is_active' => $active,
            'execution_phase' => $phase,
            'ended_at' => $active ? null : now(),
        ]);
    }

    private function driverStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'driver'],
            ['name' => $name, 'type' => 'driver']
        );
    }

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
        );
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
