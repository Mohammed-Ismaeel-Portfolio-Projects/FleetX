<?php

namespace Tests\Feature\Driver;

use App\Models\Assignment;
use App\Models\Driver;
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
 * D10 — Supplemental QR security cases.
 */
class DriverD10QrSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $inOperationStatus;

    private Status $assignedShipmentStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->inOperationStatus = Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );
        $this->assignedShipmentStatus = Status::firstOrCreate(
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment'],
            ['name' => ShipmentStatus::ASSIGNED, 'type' => 'shipment']
        );
    }

    public function test_malformed_challenge_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d10-qr-bad@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::PICKUP_VERIFYING);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/pickup/verify', [
                'challenge' => 'not-a-valid-uuid',
            ])
            ->assertStatus(422);
    }

    public function test_pickup_challenge_on_delivery_endpoint_rejected(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d10-qr-wrong-ev@fleetx.test');
        $assignment = $this->makeInOperation($driver, DriverExecutionPhase::DELIVERY_VERIFYING);
        $challenge = app(\App\Actions\Driver\IssueDriverVerificationChallengeAction::class)
            ->execute($assignment->shipment, $assignment, DriverVerificationEvent::PICKUP_VERIFY);
        $this->assertTrue($challenge->success);
        $id = $challenge->data['challenge']->id;
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/driver/assignments/'.$assignment->id.'/delivery/verify', [
                'challenge' => $id,
            ])
            ->assertStatus(409);
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

    private function makeInOperation(Driver $driver, string $phase): Assignment
    {
        $shipment = Shipment::factory()->create(['status_id' => $this->assignedShipmentStatus->id]);

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $this->inOperationStatus->id,
            'is_active' => true,
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
