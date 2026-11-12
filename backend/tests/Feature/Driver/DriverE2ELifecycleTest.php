<?php

namespace Tests\Feature\Driver;

use App\Actions\Driver\IssueDriverVerificationChallengeAction;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Models\DriverVerificationChallenge;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\DriverVerificationEvent;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * D10 — Full server-authoritative Driver lifecycle (HTTP E2E).
 */
class DriverE2ELifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $assignedStatus;

    private Status $readyStatus;

    private Status $inOperationStatus;

    private Status $completedStatus;

    private Status $assignedShipmentStatus;

    private Status $inTransitShipmentStatus;

    private Status $deliveredShipmentStatus;

    private Status $completedShipmentStatus;

    private Status $driverAvailableStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->assignedStatus = $this->makeStatus('Assigned', 'assignment');
        $this->readyStatus = $this->makeStatus('Ready', 'assignment');
        $this->inOperationStatus = $this->makeStatus('In Operation', 'assignment');
        $this->completedStatus = $this->makeStatus('Completed', 'assignment');
        $this->assignedShipmentStatus = $this->makeStatus(ShipmentStatus::ASSIGNED, 'shipment');
        $this->inTransitShipmentStatus = $this->makeStatus(ShipmentStatus::IN_TRANSIT, 'shipment');
        $this->deliveredShipmentStatus = $this->makeStatus(ShipmentStatus::DELIVERED, 'shipment');
        $this->completedShipmentStatus = $this->makeStatus(ShipmentStatus::COMPLETED, 'shipment');
        $this->driverAvailableStatus = $this->makeStatus('Available', 'driver');
        $this->makeStatus('Busy', 'driver');
        Storage::fake('local');
    }

    public function test_full_lifecycle_ack_through_complete_with_qr_pod_and_telemetry(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d10-e2e@fleetx.test');
        $assignment = $this->makeAssigned($driver);
        $token = $this->loginToken($user->email);
        $auth = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($auth)
            ->getJson('/api/v1/driver/work/active')
            ->assertOk()
            ->assertJsonPath('data.id', $assignment->id);

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/acknowledge')
            ->assertOk()
            ->assertJsonPath('data.status.key', 'ready');

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/start')
            ->assertOk()
            ->assertJsonPath('data.status.key', 'in_operation')
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_PENDING);

        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/arrive')->assertOk();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading')->assertOk();
        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading-complete')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_VERIFYING);

        $pickupChallenge = DriverVerificationChallenge::query()
            ->where('assignment_id', $assignment->id)
            ->where('event', DriverVerificationEvent::PICKUP_VERIFY)
            ->whereNull('consumed_at')
            ->firstOrFail();

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $pickupChallenge->qrPayload(),
            ])
            ->assertOk()
            ->assertJsonPath('data.assignment.execution.phase', DriverExecutionPhase::DELIVERY_EN_ROUTE)
            ->assertJsonPath('data.assignment.shipment.status.name', ShipmentStatus::IN_TRANSIT);

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'recorded_at' => now()->toIso8601String(),
                'speed' => 45,
                'heading' => 90,
                'accuracy' => 12,
            ])
            ->assertOk();

        $this->withHeaders($auth)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/trip-map')
            ->assertOk()
            ->assertJsonStructure(['data' => ['tracking_status', 'execution']]);

        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/arrive')->assertOk();
        $this->withHeaders($auth)->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/unloading')->assertOk();
        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/unloading-complete')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::DELIVERY_VERIFYING);

        $deliveryChallenge = DriverVerificationChallenge::query()
            ->where('assignment_id', $assignment->id)
            ->where('event', DriverVerificationEvent::DELIVERY_VERIFY)
            ->whereNull('consumed_at')
            ->firstOrFail();

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/verify', [
                'challenge' => $deliveryChallenge->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assignment.execution.phase', DriverExecutionPhase::DELIVERY_VERIFIED)
            ->assertJsonPath('data.assignment.shipment.status.name', ShipmentStatus::DELIVERED);

        $this->withHeaders($auth)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/outcome')
            ->assertOk()
            ->assertJsonPath('data.outcome.delivery_verified', true)
            ->assertJsonPath('data.outcome.pod_submitted', false)
            ->assertJsonPath('data.outcome.complete_eligible', false);

        $this->withHeaders($auth)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertOk()
            ->assertJsonPath('data.assignment.execution.phase', DriverExecutionPhase::POD_SUBMITTED);

        $this->withHeaders($auth)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/outcome')
            ->assertOk()
            ->assertJsonPath('data.outcome.complete_eligible', true);

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/complete')
            ->assertOk()
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.assignment.is_active', false)
            ->assertJsonPath('data.assignment.status.name', 'Completed')
            ->assertJsonPath('data.assignment.shipment.status.name', ShipmentStatus::COMPLETED);

        $this->assertFalse($assignment->fresh()->is_active);
        $this->assertSame($this->driverAvailableStatus->id, $driver->fresh()->status_id);

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/tracking', [
                'latitude' => 52.52,
                'longitude' => 13.405,
                'recorded_at' => now()->toIso8601String(),
            ])
            ->assertStatus(409);

        $this->assertGreaterThan(0, DriverNotification::query()->where('driver_id', $driver->id)->count());
    }

    private function makeStatus(string $name, string $type): Status
    {
        return Status::firstOrCreate(['name' => $name, 'type' => $type]);
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

    private function makeAssigned(Driver $driver): Assignment
    {
        $shipment = Shipment::factory()->create(['status_id' => $this->assignedShipmentStatus->id]);
        $vehicle = Vehicle::factory()->create();
        $trailer = Trailer::factory()->create();
        $dispatcher = User::factory()->create();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $this->assignedStatus->id,
            'is_active' => true,
            'execution_phase' => null,
        ]);
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function podFiles(): array
    {
        return [
            'photo' => UploadedFile::fake()->create('delivery.jpg', 40, 'image/jpeg'),
            'signature' => UploadedFile::fake()->create('signature.json', 5, 'application/json'),
            'receiver_name' => 'John Receiver',
            'condition_status' => 'good',
        ];
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
