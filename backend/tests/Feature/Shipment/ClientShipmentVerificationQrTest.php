<?php

namespace Tests\Feature\Shipment;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\DriverExecutionPhase;
use App\Support\DriverVerificationEvent;
use App\Support\ShipmentStatus;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CLIENT-QR-02 — Feature test suite for GET /api/v1/client/shipments/{shipment}/verification-qr.
 */
class ClientShipmentVerificationQrTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $adminRole;

    private Role $dispatcherRole;

    private Role $driverRole;

    private Status $statusInTransit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->adminRole = Role::where('name', 'admin')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->statusInTransit = Status::firstOrCreate(
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::IN_TRANSIT, 'type' => ShipmentStatus::TYPE]
        );
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/client/shipments/'.Str::uuid()->toString().'/verification-qr')
            ->assertUnauthorized();
    }

    public function test_admin_and_dispatcher_roles_are_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);

        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);
        $dispatcher = User::factory()->create(['role_id' => $this->dispatcherRole->id]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr')
            ->assertForbidden();

        $this->actingAs($dispatcher, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr')
            ->assertForbidden();
    }

    public function test_unlinked_client_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $shipment = $this->makeShipment($company);

        $unlinkedClient = User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => null,
        ]);

        $this->actingAs($unlinkedClient, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr')
            ->assertForbidden();
    }

    public function test_another_company_shipment_returns_404(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $clientA = $this->makeClient($companyA);
        $shipmentB = $this->makeShipment($companyB);

        $this->actingAs($clientA, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipmentB->id.'/verification-qr')
            ->assertNotFound();
    }

    public function test_unknown_id_or_invalid_uuid_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.Str::uuid()->toString().'/verification-qr')
            ->assertNotFound();

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/invalid-uuid/verification-qr')
            ->assertNotFound();
    }

    public function test_prohibited_query_parameters_are_rejected(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $client = $this->makeClient($companyA);
        $shipment = $this->makeShipment($companyA);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr?company_id='.$companyB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr?customer_company_id='.$companyB->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_company_id']);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr?shipment_owner_id=123')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipment_owner_id']);

        $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr?customer_id=123')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_returns_pickup_qr_when_phase_is_pickup_verifying(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->makeAssignment($shipment, DriverExecutionPhase::PICKUP_VERIFYING);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event', DriverVerificationEvent::PICKUP_VERIFY)
            ->assertJsonPath('data.status', 'active');

        $payload = $response->json('data.qr_payload');
        $this->assertIsString($payload);
        $this->assertStringStartsWith('fleetx://v1/verify?c=', $payload);
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_returns_delivery_qr_when_phase_is_delivery_verifying(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->makeAssignment($shipment, DriverExecutionPhase::DELIVERY_VERIFYING);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event', DriverVerificationEvent::DELIVERY_VERIFY)
            ->assertJsonPath('data.status', 'active');

        $payload = $response->json('data.qr_payload');
        $this->assertIsString($payload);
        $this->assertStringStartsWith('fleetx://v1/verify?c=', $payload);
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_returns_data_null_when_phase_is_not_verifying(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $this->makeAssignment($shipment, DriverExecutionPhase::PICKUP_PENDING);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_returns_data_null_when_no_active_assignment(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);
        $shipment = $this->makeShipment($company);

        $response = $this->actingAs($client, 'api')
            ->getJson('/api/v1/client/shipments/'.$shipment->id.'/verification-qr');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }

    private function makeShipment(Company $company, array $overrides = []): Shipment
    {
        return Shipment::factory()->create(array_merge([
            'customer_company_id' => $company->id,
            'status_id' => $this->statusInTransit->id,
            'priority' => 'normal',
            'pickup_city' => 'Warsaw',
            'pickup_country' => 'PL',
            'delivery_city' => 'Krakow',
            'delivery_country' => 'PL',
        ], $overrides));
    }

    private function makeAssignment(Shipment $shipment, string $executionPhase): Assignment
    {
        $driverUser = User::factory()->create(['role_id' => $this->driverRole->id]);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
        $vehicle = Vehicle::factory()->create();

        return Assignment::factory()->create([
            'shipment_id' => $shipment->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'is_active' => true,
            'execution_phase' => $executionPhase,
        ]);
    }
}
