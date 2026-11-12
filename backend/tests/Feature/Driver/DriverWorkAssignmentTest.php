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
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D2 — Driver work read contract (active + show) with ownership isolation.
 */
class DriverWorkAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $assignedStatus;

    private Status $completedStatus;

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
    }

    public function test_linked_driver_reads_own_active_assignment(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('drv-work-a@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, isActive: true);

        $token = $this->loginToken($user->email);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $assignment->id)
            ->assertJsonPath('data.assignment_code', $assignment->assignment_code)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.status.name', 'Assigned')
            ->assertJsonPath('data.status.key', 'assigned')
            ->assertJsonPath('data.shipment.id', $assignment->shipment_id)
            ->assertJsonMissingPath('data.engine_enrollment_status')
            ->assertJsonMissingPath('data.assigned_by')
            ->assertJsonMissingPath('data.notes')
            ->assertJsonMissingPath('data.driver_id');
    }

    public function test_active_work_returns_null_when_no_active_assignment(): void
    {
        [$user] = $this->makeLinkedDriver('drv-work-empty@fleetx.test');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_inactive_assignment_is_not_returned_as_active(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('drv-work-inactive@fleetx.test');
        $this->makeAssignmentFor($driver, isActive: false, status: $this->completedStatus);

        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_driver_can_show_own_assignment_detail(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('drv-work-detail@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, isActive: true);

        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignment->id)
            ->assertOk()
            ->assertJsonPath('data.id', $assignment->id)
            ->assertJsonPath('data.shipment.pickup.city', $assignment->shipment->pickup_city)
            ->assertJsonPath('data.vehicle.plate_number', $assignment->vehicle->plate_number);
    }

    public function test_driver_cannot_read_another_drivers_assignment(): void
    {
        [$userA] = $this->makeLinkedDriver('drv-work-a2@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('drv-work-b2@fleetx.test');
        $assignmentB = $this->makeAssignmentFor($driverB, isActive: true);

        $token = $this->loginToken($userA->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/'.$assignmentB->id)
            ->assertNotFound();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_unlinked_driver_is_forbidden(): void
    {
        $user = User::factory()->create([
            'email' => 'drv-unlinked-work@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $this->driverRole->id,
        ]);

        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active')
            ->assertForbidden();
    }

    public function test_unauthenticated_is_denied(): void
    {
        $this->getJson('/api/v1/driver/work/active')->assertUnauthorized();
    }

    public function test_client_role_cannot_use_driver_work_api(): void
    {
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $user = User::factory()->create([
            'email' => 'client-work@fleetx.test',
            'password' => Hash::make('password'),
            'role_id' => $clientRole->id,
        ]);

        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active')
            ->assertForbidden();
    }

    public function test_driver_id_query_param_is_prohibited(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('drv-work-probe@fleetx.test');
        $this->makeAssignmentFor($driver, isActive: true);
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active?driver_id=999')
            ->assertStatus(422);
    }

    public function test_dto_does_not_leak_forbidden_fields(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('drv-work-leak@fleetx.test');
        $assignment = $this->makeAssignmentFor($driver, isActive: true);
        $token = $this->loginToken($user->email);

        $data = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/work/active')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('engine_enrollment_status', $data);
        $this->assertArrayNotHasKey('engine_enrolled_at', $data);
        $this->assertArrayNotHasKey('assigned_by', $data);
        $this->assertArrayNotHasKey('notes', $data);
        $this->assertArrayNotHasKey('deleted_at', $data);
        $this->assertArrayNotHasKey('driver_id', $data);
        $this->assertArrayNotHasKey('created_by', $data);
        $this->assertArrayNotHasKey('updated_by', $data);
        $this->assertSame($assignment->id, $data['id']);
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

    private function makeAssignmentFor(Driver $driver, bool $isActive, ?Status $status = null): Assignment
    {
        $shipment = Shipment::factory()->create([
            'pickup_company' => 'Pickup Co',
            'pickup_city' => 'Berlin',
            'pickup_country' => 'DE',
            'delivery_company' => 'Delivery Co',
            'delivery_city' => 'Munich',
            'delivery_country' => 'DE',
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
            'status_id' => ($status ?? $this->assignedStatus)->id,
            'is_active' => $isActive,
            'notes' => 'OPS INTERNAL NOTE MUST NOT LEAK',
            'engine_enrollment_status' => 'enrolled',
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
