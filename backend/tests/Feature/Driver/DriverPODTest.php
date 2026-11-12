<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\DeliveryProof;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * D7 + POD-02 — Proof of Delivery & Delivery Evidence Foundation.
 */
class DriverPODTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $completedStatus;

    private Status $cancelledStatus;

    private Status $deliveredShipmentStatus;

    private Status $inTransitShipmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->completedStatus = $this->assignmentStatus('Completed');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        $this->deliveredShipmentStatus = $this->shipmentStatus(ShipmentStatus::DELIVERED);
        $this->inTransitShipmentStatus = $this->shipmentStatus(ShipmentStatus::IN_TRANSIT);
        Storage::fake('local');
    }

    /** Test 1: Driver submits complete POD evidence */
    public function test_own_driver_can_submit_complete_pod_evidence(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-own@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver);
        $token = $this->loginToken($user->email);

        $payload = array_merge($this->podFiles(), [
            'receiver_name' => 'Ahmed Khalil',
            'receiver_role' => 'Warehouse Manager',
            'condition_status' => 'good',
            'latitude' => 24.7136000,
            'longitude' => 46.6753000,
            'delivery_notes' => 'Received in perfect condition.',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $payload)
            ->assertOk()
            ->assertJsonPath('data.pod.submitted', true)
            ->assertJsonPath('data.pod.receiver_name', 'Ahmed Khalil')
            ->assertJsonPath('data.pod.receiver_role', 'Warehouse Manager')
            ->assertJsonPath('data.pod.condition_status', 'good')
            ->assertJsonPath('data.pod.delivery_notes', 'Received in perfect condition.')
            ->assertJsonPath('data.pod.assignment_id', $assignment->id)
            ->assertJsonPath('data.pod.driver_id', $driver->id)
            ->assertJsonPath('data.assignment.execution.phase', DriverExecutionPhase::POD_SUBMITTED);

        $this->assertDatabaseHas('delivery_proofs', [
            'shipment_id' => $assignment->shipment_id,
            'assignment_id' => $assignment->id,
            'driver_id' => $driver->id,
            'receiver_name' => 'Ahmed Khalil',
            'receiver_role' => 'Warehouse Manager',
            'condition_status' => 'good',
            'delivery_notes' => 'Received in perfect condition.',
        ]);

        $proof = DeliveryProof::where('shipment_id', $assignment->shipment_id)->first();
        $this->assertNotNull($proof);
        $this->assertEquals(24.7136000, (float) $proof->latitude);
        $this->assertEquals(46.6753000, (float) $proof->longitude);
        $this->assertStringContainsString('driver-pod/', $proof->image_url);
    }

    /** Test 2: Missing receiver_name returns 422 validation error */
    public function test_missing_receiver_name_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-noname@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver);
        $token = $this->loginToken($user->email);

        $payload = $this->podFiles();
        unset($payload['receiver_name']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['receiver_name']);

        $this->assertSame(0, DeliveryProof::count());
    }

    /** Test 3: Invalid condition_status returns 422 validation error */
    public function test_invalid_condition_status_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-badcond@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver);
        $token = $this->loginToken($user->email);

        $payload = array_merge($this->podFiles(), [
            'condition_status' => 'lost',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['condition_status']);

        $this->assertSame(0, DeliveryProof::count());
    }

    /** Test 4: Driver cannot submit POD for another driver's assignment */
    public function test_other_driver_denied_pod(): void
    {
        [, $owner] = $this->makeLinkedDriver('pod02-owner@fleetx.test');
        [$otherUser] = $this->makeLinkedDriver('pod02-other@fleetx.test');
        $assignment = $this->makeDeliveredVerified($owner);
        $token = $this->loginToken($otherUser->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertNotFound();
    }

    /** Test 5: Duplicate POD is idempotent */
    public function test_duplicate_pod_is_idempotent(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-dup@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertOk()
            ->assertJsonPath('data.pod.submitted', true);

        $this->assertSame(1, DeliveryProof::where('shipment_id', $assignment->shipment_id)->count());
    }

    public function test_wrong_phase_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-phase@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::DELIVERY_VERIFYING,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertStatus(409);
    }

    public function test_not_delivered_shipment_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-ship@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::DELIVERY_VERIFIED,
            $this->inTransitShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertStatus(409);
    }

    public function test_cancelled_assignment_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-cancel@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->cancelledStatus,
            false,
            DriverExecutionPhase::DELIVERY_VERIFIED,
            $this->deliveredShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertStatus(409);
    }

    public function test_completed_assignment_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-done@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->completedStatus,
            false,
            DriverExecutionPhase::POD_SUBMITTED,
            $this->deliveredShipmentStatus
        );
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertStatus(409);
    }

    public function test_malformed_upload_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-bad@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver);
        $token = $this->loginToken($user->email);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', [
                'photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
                'signature' => UploadedFile::fake()->create('sig.png', 10, 'image/png'),
                'receiver_name' => 'John Receiver',
                'condition_status' => 'good',
            ])
            ->assertStatus(422);

        $this->assertSame(0, DeliveryProof::count());
    }

    public function test_foreign_shipment_id_cannot_override(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('pod02-fid@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver);
        $foreign = Shipment::factory()->create(['status_id' => $this->deliveredShipmentStatus->id]);
        $token = $this->loginToken($user->email);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', array_merge($this->podFiles(), [
                'shipment_id' => $foreign->id,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseMissing('delivery_proofs', ['shipment_id' => $foreign->id]);
    }

    public function test_unauthenticated_denied(): void
    {
        [, $driver] = $this->makeLinkedDriver('pod02-unauth@fleetx.test');
        $assignment = $this->makeDeliveredVerified($driver);

        $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/driver/assignments/'.$assignment->id.'/pod', $this->podFiles())
            ->assertUnauthorized();
    }

    /**
     * @return array<string, mixed>
     */
    private function podFiles(): array
    {
        return [
            'photo' => UploadedFile::fake()->create('delivery.jpg', 40, 'image/jpeg'),
            'signature' => UploadedFile::fake()->create('signature.png', 20, 'image/png'),
            'receiver_name' => 'John Receiver',
            'receiver_role' => 'Warehouse Manager',
            'condition_status' => 'good',
            'latitude' => 24.7136000,
            'longitude' => 46.6753000,
            'delivery_notes' => 'Received in good condition.',
        ];
    }

    private function makeDeliveredVerified(Driver $driver): Assignment
    {
        return $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::DELIVERY_VERIFIED,
            $this->deliveredShipmentStatus
        );
    }

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
        );
    }

    private function shipmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'shipment'],
            ['name' => $name, 'type' => 'shipment']
        );
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

    private function makeAssignmentFor(
        Driver $driver,
        Status $status,
        bool $isActive,
        ?string $phase,
        Status $shipmentStatus
    ): Assignment {
        $shipment = Shipment::factory()->create(['status_id' => $shipmentStatus->id]);
        $vehicle = Vehicle::factory()->create();
        $trailer = Trailer::factory()->create();
        $dispatcher = User::factory()->create();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $dispatcher->id,
            'status_id' => $status->id,
            'is_active' => $isActive,
            'execution_phase' => $phase,
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
