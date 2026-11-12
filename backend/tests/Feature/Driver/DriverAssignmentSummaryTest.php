<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\DeliveryProof;
use App\Models\Driver;
use App\Models\DriverVerificationChallenge;
use App\Models\Issue;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\DriverIssueType;
use App\Support\DriverVerificationEvent;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D11 — Historical assignment summary (read-only).
 */
class DriverAssignmentSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $assignedStatus;

    private Status $completedStatus;

    private Status $cancelledStatus;

    private Status $openIssueStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->assignedStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['name' => 'Assigned', 'type' => 'assignment']
        );
        $this->completedStatus = Status::firstOrCreate(
            ['name' => 'Completed', 'type' => 'assignment'],
            ['name' => 'Completed', 'type' => 'assignment']
        );
        $this->cancelledStatus = Status::firstOrCreate(
            ['name' => 'Cancelled', 'type' => 'assignment'],
            ['name' => 'Cancelled', 'type' => 'assignment']
        );
        $this->openIssueStatus = Status::firstOrCreate(
            ['name' => 'Open', 'type' => 'issue'],
            ['name' => 'Open', 'type' => 'issue']
        );
    }

    public function test_own_historical_summary_includes_safe_pod_and_exceptions(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-sum-own@fleetx.test');
        $assignment = $this->makeCompleted($driver, withProof: true);
        $verifiedAt = now()->subHour();
        DriverVerificationChallenge::query()->create([
            'shipment_id' => $assignment->shipment_id,
            'assignment_id' => $assignment->id,
            'event' => DriverVerificationEvent::PICKUP_VERIFY,
            'expires_at' => now()->addHour(),
            'consumed_at' => $verifiedAt,
            'consumed_by_user_id' => $user->id,
        ]);
        DriverVerificationChallenge::query()->create([
            'shipment_id' => $assignment->shipment_id,
            'assignment_id' => $assignment->id,
            'event' => DriverVerificationEvent::DELIVERY_VERIFY,
            'expires_at' => now()->addHour(),
            'consumed_at' => $verifiedAt->copy()->addMinutes(30),
            'consumed_by_user_id' => $user->id,
        ]);
        Issue::query()->create([
            'shipment_id' => $assignment->shipment_id,
            'driver_id' => $driver->id,
            'type' => DriverIssueType::TRAFFIC,
            'description' => 'Heavy traffic on coastal road',
            'status_id' => $this->openIssueStatus->id,
        ]);

        $token = $this->loginToken($user->email);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary');

        $response->assertOk()
            ->assertJsonPath('data.id', $assignment->id)
            ->assertJsonPath('data.outcome.key', 'completed')
            ->assertJsonPath('data.shipment.pickup.company', 'Pickup Co')
            ->assertJsonPath('data.shipment.delivery.company', 'Delivery Co')
            ->assertJsonPath('data.verification.pickup.verified', true)
            ->assertJsonPath('data.verification.delivery.verified', true)
            ->assertJsonPath('data.proof.exists', true)
            ->assertJsonPath('data.exceptions.0.type', 'traffic')
            ->assertJsonPath('data.exceptions.0.label', 'Traffic');

        $payload = $response->json('data');
        $this->assertArrayNotHasKey('image_url', $payload['proof']);
        $this->assertArrayNotHasKey('signature', $payload['proof']);
        $this->assertArrayNotHasKey('driver_id', $payload);
        $this->assertArrayNotHasKey('notes', $payload);
        $this->assertArrayNotHasKey('assigned_by', $payload);
        $this->assertContains('photo', $payload['proof']['types']);
        $this->assertContains('signature', $payload['proof']['types']);
    }

    public function test_active_assignment_summary_is_conflict(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-sum-active@fleetx.test');
        $active = Assignment::factory()->create([
            'shipment_id' => Shipment::factory()->create()->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->assignedStatus->id,
            'is_active' => true,
        ]);

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$active->id.'/summary')
            ->assertStatus(409);
    }

    public function test_other_driver_denied_not_found(): void
    {
        [$userA] = $this->makeLinkedDriver('d11-sum-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d11-sum-b@fleetx.test');
        $other = $this->makeCompleted($driverB, withProof: false);

        $token = $this->loginToken($userA->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$other->id.'/summary')
            ->assertNotFound();
    }

    public function test_summary_driver_id_query_prohibited(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-sum-spoof@fleetx.test');
        $assignment = $this->makeCompleted($driver, withProof: false);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary?driver_id=1')
            ->assertStatus(422);
    }

    public function test_cancelled_without_proof_is_honest(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-sum-cancel@fleetx.test');
        $assignment = Assignment::factory()->create([
            'shipment_id' => Shipment::factory()->create([
                'pickup_company' => 'Pickup Co',
                'delivery_company' => 'Delivery Co',
            ])->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->cancelledStatus->id,
            'is_active' => false,
            'execution_phase' => DriverExecutionPhase::PICKUP_PENDING,
            'ended_at' => now(),
        ]);

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id.'/summary')
            ->assertOk()
            ->assertJsonPath('data.outcome.key', 'cancelled')
            ->assertJsonPath('data.proof.exists', false)
            ->assertJsonPath('data.verification.pickup.verified', false)
            ->assertJsonPath('data.exceptions', []);
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

    private function makeCompleted(Driver $driver, bool $withProof): Assignment
    {
        $shipment = Shipment::factory()->create([
            'pickup_company' => 'Pickup Co',
            'pickup_city' => 'Beirut',
            'delivery_company' => 'Delivery Co',
            'delivery_city' => 'Tripoli',
        ]);
        if ($withProof) {
            DeliveryProof::query()->create([
                'shipment_id' => $shipment->id,
                'image_url' => 'driver-pod/'.$shipment->id.'/photo.jpg',
                'signature' => 'driver-pod/'.$shipment->id.'/signature.json',
                'confirmed_at' => now(),
            ]);
        }

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
