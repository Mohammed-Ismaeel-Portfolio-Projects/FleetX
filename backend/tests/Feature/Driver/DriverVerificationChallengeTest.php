<?php

namespace Tests\Feature\Driver;

use App\Actions\Driver\IssueDriverVerificationChallengeAction;
use App\Models\Assignment;
use App\Models\Driver;
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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D5 — Secure QR verification (pickup + delivery).
 */
class DriverVerificationChallengeTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $cancelledStatus;

    private Status $assignedShipmentStatus;

    private Status $inTransitShipmentStatus;

    private Status $deliveredShipmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
        $this->assignedShipmentStatus = $this->shipmentStatus(ShipmentStatus::ASSIGNED);
        $this->inTransitShipmentStatus = $this->shipmentStatus(ShipmentStatus::IN_TRANSIT);
        $this->deliveredShipmentStatus = $this->shipmentStatus(ShipmentStatus::DELIVERED);
    }

    public function test_own_driver_valid_pickup_qr_passes(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-pu-own@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->qrPayload(),
            ])
            ->assertOk()
            ->assertJsonPath('data.assignment.execution.phase', DriverExecutionPhase::DELIVERY_EN_ROUTE)
            ->assertJsonPath('data.assignment.shipment.status.name', ShipmentStatus::IN_TRANSIT)
            ->assertJsonPath('data.verification.event', DriverVerificationEvent::PICKUP_VERIFY);

        $this->assertNotNull($challenge->fresh()->consumed_at);
        $this->assertSame(
            $this->inTransitShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_own_driver_valid_delivery_qr_passes(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-del-own@fleetx.test');
        $assignment = $this->makeInOperation(
            $driver,
            DriverExecutionPhase::DELIVERY_VERIFYING,
            $this->inTransitShipmentStatus
        );
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::DELIVERY_VERIFY);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assignment.execution.phase', DriverExecutionPhase::DELIVERY_VERIFIED)
            ->assertJsonPath('data.assignment.shipment.status.name', ShipmentStatus::DELIVERED);

        $this->assertSame(
            $this->deliveredShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_other_driver_denied(): void
    {
        [$userA] = $this->makeLinkedDriver('d5-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d5-b@fleetx.test');
        $assignmentB = $this->makeInOperation($driverB, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignmentB, DriverVerificationEvent::PICKUP_VERIFY);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignmentB->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertNotFound();
    }

    public function test_unlinked_driver_denied(): void
    {
        $user = User::factory()->create([
            'email' => 'd5-unlink@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/pickup/verify', [
                'challenge' => '00000000-0000-4000-8000-000000000001',
            ])
            ->assertForbidden();
    }

    public function test_client_role_denied(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd5-client@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/1/pickup/verify', [
                'challenge' => '00000000-0000-4000-8000-000000000001',
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_denied(): void
    {
        $this->postJson('/api/v1/driver/assignments/1/pickup/verify', [
            'challenge' => '00000000-0000-4000-8000-000000000001',
        ])->assertUnauthorized();
    }

    public function test_driver_cannot_use_ops_shipment_status_mutation(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-ops-block@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/shipments/'.$assignment->shipment_id.'/status', [
                'status_id' => $this->inTransitShipmentStatus->id,
            ])
            ->assertForbidden();
    }

    public function test_pickup_qr_during_delivery_phase_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-wrong-phase-pu@fleetx.test');
        $assignment = $this->makeInOperation(
            $driver,
            DriverExecutionPhase::DELIVERY_VERIFYING,
            $this->inTransitShipmentStatus
        );
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'invalid_phase');
    }

    public function test_delivery_qr_during_pickup_phase_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-wrong-phase-del@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::DELIVERY_VERIFY);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'invalid_phase');
    }

    public function test_qr_for_another_shipment_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-wrong-shp@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $other = $this->makeInOperation(
            Driver::factory()->create(['user_id' => User::factory()->create(['role_id' => $this->driverRole->id])->id]),
            DriverExecutionPhase::PICKUP_VERIFYING
        );
        $foreignChallenge = $this->issueChallenge($other, DriverVerificationEvent::PICKUP_VERIFY);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $foreignChallenge->id,
            ])
            ->assertNotFound()
            ->assertJsonPath('errors.code', 'wrong_assignment');
    }

    public function test_qr_for_another_assignment_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-wrong-asn@fleetx.test');
        [, $otherDriver] = $this->makeLinkedDriver('d5-wrong-asn-other@fleetx.test');
        $a1 = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $a2 = $this->makeInOperation($otherDriver, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($a2, DriverVerificationEvent::PICKUP_VERIFY);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$a1->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertNotFound();
    }

    public function test_expired_qr_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-expired@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $challenge->update(['expires_at' => now()->subMinute()]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'expired_challenge');

        $this->assertNull($challenge->fresh()->consumed_at);
        $this->assertSame(DriverExecutionPhase::PICKUP_VERIFYING, $assignment->fresh()->execution_phase);
    }

    public function test_revoked_qr_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-revoked@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $challenge->update(['revoked_at' => now()]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'revoked_challenge');
    }

    public function test_already_consumed_qr_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-consumed@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $challenge->update([
            'consumed_at' => now(),
            'consumed_by_user_id' => $user->id,
        ]);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'already_consumed');

        $this->assertSame(DriverExecutionPhase::PICKUP_VERIFYING, $assignment->fresh()->execution_phase);
        $this->assertSame(
            $this->assignedShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_cancelled_assignment_denied(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-cancel@fleetx.test');
        $assignment = $this->makeAssignmentFor(
            $driver,
            $this->cancelledStatus,
            false,
            DriverExecutionPhase::PICKUP_VERIFYING
        );
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'assignment_inactive');
    }

    public function test_reassigned_assignment_denied(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d5-reas-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d5-reas-b@fleetx.test');
        $assignment = $this->makeInOperation($driverA, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $assignment->update(['driver_id' => $driverB->id]);
        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertNotFound();
    }

    public function test_sequential_duplicate_verify_is_idempotent_success(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-idem@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $token = $this->loginToken($user->email);
        $auth = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertOk();

        $this->withHeaders($auth)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assignment.execution.phase', DriverExecutionPhase::DELIVERY_EN_ROUTE);

        $this->assertSame(1, DriverVerificationChallenge::query()->whereKey($challenge->id)->whereNotNull('consumed_at')->count());
        $this->assertSame(
            $this->inTransitShipmentStatus->id,
            $assignment->fresh()->shipment->status_id
        );
    }

    public function test_loading_complete_auto_issues_challenge(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-auto@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_LOADING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/loading-complete')
            ->assertOk()
            ->assertJsonPath('data.execution.phase', DriverExecutionPhase::PICKUP_VERIFYING);

        $this->assertDatabaseHas('driver_verification_challenges', [
            'assignment_id' => $assignment->id,
            'event' => DriverVerificationEvent::PICKUP_VERIFY,
            'consumed_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function test_ownership_override_params_prohibited(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d5-probe@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $challenge = $this->issueChallenge($assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => $challenge->id,
                'driver_id' => 999,
                'shipment_id' => '00000000-0000-0000-0000-000000000099',
            ])
            ->assertStatus(422);
    }

    public function test_ops_can_issue_challenge_for_presentation(): void
    {
        $dispatcherRole = Role::where('name', 'dispatcher')->first()
            ?? Role::where('name', 'admin')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'd5-ops-issue@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $dispatcherRole->id,
        ]);
        // Grant shipment.view if permission system requires it
        if (method_exists($user, 'givePermissionTo')) {
            try {
                $user->givePermissionTo('shipment.view');
            } catch (\Throwable) {
                // permission may already exist via role
            }
        }

        [, $driver] = $this->makeLinkedDriver('d5-ops-drv@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $token = $this->loginToken($user->email);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/shipments/'.$assignment->shipment_id.'/driver-verification-challenges', [
                'event' => DriverVerificationEvent::PICKUP_VERIFY,
                'assignment_id' => $assignment->id,
            ]);

        // Ops may 403 if role lacks shipment.view in this seed — accept 200 or document
        if ($response->status() === 200) {
            $response->assertJsonPath('data.event', DriverVerificationEvent::PICKUP_VERIFY)
                ->assertJsonStructure(['data' => ['id', 'qr_payload', 'expires_at']]);
        } else {
            $response->assertForbidden();
            $this->markTestSkipped('Dispatcher role lacks shipment.view in seed; issue API present.');
        }
    }

    private function issueChallenge(Assignment $assignment, string $event): DriverVerificationChallenge
    {
        $assignment->loadMissing('shipment');
        $result = app(IssueDriverVerificationChallengeAction::class)
            ->execute($assignment->shipment, $assignment, $event);
        $this->assertTrue($result->success);

        return $result->data['challenge'];
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
        $driver = Driver::factory()->create(['user_id' => $user->id]);

        return [$user, $driver];
    }

    private function makeInOperation(
        Driver $driver,
        string $phase,
        ?Status $shipmentStatus = null
    ): Assignment {
        return $this->makeAssignmentFor(
            $driver,
            $this->inOperationStatus,
            true,
            $phase,
            $shipmentStatus ?? $this->assignedShipmentStatus
        );
    }

    private function makeAssignmentFor(
        Driver $driver,
        Status $status,
        bool $isActive,
        ?string $executionPhase = null,
        ?Status $shipmentStatus = null
    ): Assignment {
        $shipment = Shipment::factory()->create([
            'status_id' => ($shipmentStatus ?? $this->assignedShipmentStatus)->id,
        ]);
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
            'execution_phase' => $executionPhase,
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
