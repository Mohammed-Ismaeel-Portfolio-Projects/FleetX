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
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D11 — Driver assignment history list.
 */
class DriverAssignmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Role $driverRole;

    private Status $assignedStatus;

    private Status $inOperationStatus;

    private Status $completedStatus;

    private Status $cancelledStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();
        $this->assignedStatus = $this->assignmentStatus('Assigned');
        $this->inOperationStatus = $this->assignmentStatus('In Operation');
        $this->completedStatus = $this->assignmentStatus('Completed');
        $this->cancelledStatus = $this->assignmentStatus('Cancelled');
    }

    public function test_own_completed_assignment_appears_in_history(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-hist-own@fleetx.test');
        $completed = $this->makeHistorical($driver, $this->completedStatus, DriverExecutionPhase::POD_SUBMITTED);
        $this->makeActive($driver);

        $token = $this->loginToken($user->email);
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $completed->id)
            ->assertJsonPath('data.data.0.outcome.key', 'completed')
            ->assertJsonPath('data.data.0.outcome.label', 'Completed')
            ->assertJsonPath('data.data.0.pickup.company', 'Pickup Co')
            ->assertJsonPath('data.data.0.delivery.company', 'Delivery Co')
            ->assertJsonMissingPath('data.data.0.driver_id')
            ->assertJsonMissingPath('data.data.0.notes')
            ->assertJsonMissingPath('data.data.0.assigned_by')
            ->assertJsonMissingPath('data.data.0.engine_enrollment_status');
    }

    public function test_cancelled_failed_delivery_appears_as_failed_outcome(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-hist-fail@fleetx.test');
        $failed = $this->makeHistorical(
            $driver,
            $this->cancelledStatus,
            DriverExecutionPhase::DELIVERY_FAILED
        );

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $failed->id)
            ->assertJsonPath('data.data.0.outcome.key', 'failed')
            ->assertJsonPath('data.data.0.status.key', 'cancelled');
    }

    public function test_active_assignment_is_excluded_from_history(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-hist-active@fleetx.test');
        $this->makeActive($driver);

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_active_failed_delivery_is_excluded_from_history(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-hist-active-fail@fleetx.test');
        $this->makeInOperationFailed($driver);

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_driver_cannot_see_another_drivers_history(): void
    {
        [$userA, $driverA] = $this->makeLinkedDriver('d11-hist-a@fleetx.test');
        [, $driverB] = $this->makeLinkedDriver('d11-hist-b@fleetx.test');
        $this->makeHistorical($driverA, $this->completedStatus, DriverExecutionPhase::POD_SUBMITTED);
        $other = $this->makeHistorical($driverB, $this->completedStatus, DriverExecutionPhase::POD_SUBMITTED);

        $token = $this->loginToken($userA->email);
        $ids = collect($this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history')
            ->assertOk()
            ->json('data.data'))->pluck('id');

        $this->assertTrue($ids->doesntContain($other->id));
        $this->assertCount(1, $ids);
    }

    public function test_pagination_works(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-hist-page@fleetx.test');
        $this->makeHistorical($driver, $this->completedStatus, DriverExecutionPhase::POD_SUBMITTED);
        $this->makeHistorical($driver, $this->cancelledStatus, null);
        $this->makeHistorical($driver, $this->completedStatus, DriverExecutionPhase::POD_SUBMITTED);

        $token = $this->loginToken($user->email);
        $page1 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history?page=1&per_page=2')
            ->assertOk();
        $page1->assertJsonPath('data.meta.per_page', 2)
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonPath('data.meta.last_page', 2);
        $this->assertCount(2, $page1->json('data.data'));

        $page2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history?page=2&per_page=2')
            ->assertOk();
        $this->assertCount(1, $page2->json('data.data'));
    }

    public function test_status_filter_completed_only(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-hist-filter@fleetx.test');
        $done = $this->makeHistorical($driver, $this->completedStatus, DriverExecutionPhase::POD_SUBMITTED);
        $this->makeHistorical($driver, $this->cancelledStatus, null);

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history?status=completed')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $done->id);
    }

    public function test_soft_deleted_assignment_is_excluded_from_history(): void
    {
        [$user, $driver] = $this->makeLinkedDriver('d11-hist-deleted@fleetx.test');
        $deleted = $this->makeHistorical($driver, $this->completedStatus, DriverExecutionPhase::POD_SUBMITTED);
        $deleted->delete();

        $token = $this->loginToken($user->email);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonPath('data.data', []);
    }

    public function test_driver_id_query_is_prohibited(): void
    {
        [$user] = $this->makeLinkedDriver('d11-hist-spoof@fleetx.test');
        $token = $this->loginToken($user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/driver/assignments/history?driver_id=1')
            ->assertStatus(422);
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

    private function assignmentStatus(string $name): Status
    {
        return Status::firstOrCreate(
            ['name' => $name, 'type' => 'assignment'],
            ['name' => $name, 'type' => 'assignment']
        );
    }

    private function makeHistorical(Driver $driver, Status $status, ?string $phase): Assignment
    {
        return $this->makeAssignment($driver, $status, false, $phase);
    }

    private function makeActive(Driver $driver): Assignment
    {
        return $this->makeAssignment($driver, $this->assignedStatus, true, null);
    }

    private function makeInOperationFailed(Driver $driver): Assignment
    {
        return $this->makeAssignment(
            $driver,
            $this->inOperationStatus,
            true,
            DriverExecutionPhase::DELIVERY_FAILED
        );
    }

    private function makeAssignment(Driver $driver, Status $status, bool $active, ?string $phase): Assignment
    {
        $shipment = Shipment::factory()->create([
            'pickup_company' => 'Pickup Co',
            'pickup_city' => 'Beirut',
            'pickup_country' => 'LB',
            'delivery_company' => 'Delivery Co',
            'delivery_city' => 'Tripoli',
            'delivery_country' => 'LB',
        ]);

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'trailer_id' => Trailer::factory()->create()->id,
            'assigned_by' => User::factory()->create()->id,
            'status_id' => $status->id,
            'is_active' => $active,
            'execution_phase' => $phase,
            'assigned_at' => now()->subDay(),
            'ended_at' => $active ? null : now(),
            'notes' => 'OPS NOTE MUST NOT LEAK',
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
