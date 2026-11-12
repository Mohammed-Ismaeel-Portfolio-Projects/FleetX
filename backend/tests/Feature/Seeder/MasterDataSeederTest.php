<?php

namespace Tests\Feature\Seeder;

use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use App\Support\ShipmentStatus;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterDataSeeder::class);

        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $this->dispatcher = User::where('email', 'dispatcher@fleetx.io')->first()
            ?? User::factory()->create(['role_id' => $dispatcherRole->id]);
    }

    public function test_seeder_creates_valid_drivers(): void
    {
        $drivers = Driver::query()->where('employee_number', 'LIKE', 'EMP-%')->get();
        $this->assertGreaterThanOrEqual(10, $drivers->count());

        foreach ($drivers as $driver) {
            $this->assertNotNull($driver->first_name);
            $this->assertNotNull($driver->last_name);
            $this->assertNotNull($driver->license_number);
            $this->assertNotNull($driver->status_id);
            $this->assertTrue($driver->is_active);
        }
    }

    public function test_seeder_creates_valid_vehicles(): void
    {
        $vehicles = Vehicle::query()->where('operational_code', 'LIKE', 'VH-%')->get();
        $this->assertGreaterThanOrEqual(10, $vehicles->count());

        foreach ($vehicles as $vehicle) {
            $this->assertNotNull($vehicle->fleet_number);
            $this->assertNotNull($vehicle->plate_number);
            $this->assertNotNull($vehicle->vin);
            $this->assertGreaterThan(0, $vehicle->payload_capacity_kg);
            $this->assertTrue($vehicle->is_active);
        }
    }

    public function test_seeder_creates_valid_trailers(): void
    {
        $trailers = Trailer::query()->where('trailer_code', 'LIKE', 'TR-%')->get();
        $this->assertGreaterThanOrEqual(10, $trailers->count());

        foreach ($trailers as $trailer) {
            $this->assertNotNull($trailer->fleet_number);
            $this->assertNotNull($trailer->plate_number);
            $this->assertNotNull($trailer->trailer_type);
            $this->assertGreaterThan(0, $trailer->payload_capacity_kg);
            $this->assertTrue($trailer->is_active);
        }
    }

    public function test_seeder_creates_valid_clients(): void
    {
        $companies = Company::query()->where('company_code', 'LIKE', 'CMP-%')->get();
        $this->assertGreaterThanOrEqual(8, $companies->count());

        foreach ($companies as $company) {
            $this->assertNotNull($company->company_name);
            $this->assertNotNull($company->city);
            $this->assertNotNull($company->country);
            $this->assertNotNull($company->primary_email);
        }
    }

    public function test_seeder_creates_valid_assignments(): void
    {
        $assignments = Assignment::query()->where('assignment_code', 'LIKE', 'ASG-%')->get();
        $this->assertGreaterThanOrEqual(5, $assignments->count());

        foreach ($assignments as $assignment) {
            $this->assertNotNull($assignment->shipment_id);
            $this->assertNotNull($assignment->driver_id);
            $this->assertNotNull($assignment->vehicle_id);
            $this->assertNotNull($assignment->trailer_id);
            $this->assertNotNull($assignment->assigned_by);
        }
    }

    public function test_assigned_shipment_exposes_driver_truck_and_trailer_in_api(): void
    {
        $shipment = Shipment::where('shipment_number', 'SHP-20001')->firstOrFail();

        $response = $this->actingAs($this->dispatcher, 'api')
            ->getJson("/api/v1/shipments/{$shipment->id}");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotNull($data['active_assignment']);
        $this->assertNotNull($data['active_assignment']['driver']);
        $this->assertNotNull($data['active_assignment']['vehicle']);
        $this->assertNotNull($data['active_assignment']['trailer']);
    }

    public function test_in_transit_shipment_appears_in_active_tracking(): void
    {
        $inTransitShipment = Shipment::where('shipment_number', 'SHP-20002')->firstOrFail();

        $response = $this->actingAs($this->dispatcher, 'api')
            ->getJson('/api/v1/tracking/live-fleet');

        $response->assertOk();
        $vehicles = $response->json('data.vehicles');

        $shipmentIds = array_column($vehicles, 'shipment_id');
        $this->assertContains($inTransitShipment->id, $shipmentIds);
    }

    public function test_seeder_is_idempotent(): void
    {
        $driverCountBefore = Driver::count();
        $vehicleCountBefore = Vehicle::count();
        $trailerCountBefore = Trailer::count();
        $companyCountBefore = Company::count();
        $shipmentCountBefore = Shipment::count();
        $assignmentCountBefore = Assignment::count();

        // Run MasterDataSeeder a second time
        $this->seed(MasterDataSeeder::class);

        $this->assertSame($driverCountBefore, Driver::count());
        $this->assertSame($vehicleCountBefore, Vehicle::count());
        $this->assertSame($trailerCountBefore, Trailer::count());
        $this->assertSame($companyCountBefore, Company::count());
        $this->assertSame($shipmentCountBefore, Shipment::count());
        $this->assertSame($assignmentCountBefore, Assignment::count());
    }

    public function test_dispatcher_assignment_apis_return_seeded_entities(): void
    {
        $driversResp = $this->actingAs($this->dispatcher, 'api')->getJson('/api/v1/drivers');
        $driversResp->assertOk();
        $this->assertGreaterThanOrEqual(10, count($driversResp->json('data.data')));

        $vehiclesResp = $this->actingAs($this->dispatcher, 'api')->getJson('/api/v1/vehicles');
        $vehiclesResp->assertOk();
        $this->assertGreaterThanOrEqual(10, count($vehiclesResp->json('data.data')));

        $trailersResp = $this->actingAs($this->dispatcher, 'api')->getJson('/api/v1/trailers');
        $trailersResp->assertOk();
        $this->assertGreaterThanOrEqual(10, count($trailersResp->json('data.data')));
    }

    public function test_reserved_viva_clients_exist_and_are_active(): void
    {
        $vivaClients = [
            'client1@fleetx.com' => 'FLeetX Demo Logistics 01',
            'client2@fleetx.com' => 'FLeetX Demo Logistics 02',
            'client3@fleetx.com' => 'FLeetX Demo Logistics 03',
        ];

        foreach ($vivaClients as $email => $name) {
            $user = User::where('email', $email)->first();
            $this->assertNotNull($user, "Viva client user {$email} must exist.");

            $company = Company::where('primary_email', $email)->first();
            $this->assertNotNull($company, "Viva company for {$email} must exist.");
            $this->assertSame($name, $company->company_name);
        }
    }

    public function test_reserved_viva_drivers_exist_and_are_available(): void
    {
        $vivaDrivers = [
            'driver1@fleetx.com' => 'Ahmad',
            'driver2@fleetx.com' => 'Omar',
            'driver3@fleetx.com' => 'Khaled',
        ];

        foreach ($vivaDrivers as $email => $firstName) {
            $driver = Driver::where('email', $email)->first();
            $this->assertNotNull($driver, "Viva driver {$email} must exist.");
            $this->assertSame($firstName, $driver->first_name);
            $this->assertTrue($driver->is_active);
            $this->assertSame('Available', $driver->status?->name);
            $this->assertNull($driver->activeAssignment);
        }
    }

    public function test_reserved_viva_vehicles_exist_and_are_paired(): void
    {
        $expectedPairs = [
            'driver1@fleetx.com' => 'VH-20011',
            'driver2@fleetx.com' => 'VH-20012',
            'driver3@fleetx.com' => 'VH-20013',
        ];

        foreach ($expectedPairs as $driverEmail => $vehicleCode) {
            $driver = Driver::where('email', $driverEmail)->firstOrFail();
            $vehicle = Vehicle::where('operational_code', $vehicleCode)->first();

            $this->assertNotNull($vehicle, "Vehicle {$vehicleCode} must exist.");
            $this->assertSame($driver->id, $vehicle->driver_id);
            $this->assertTrue($vehicle->is_active);
            $this->assertSame('Available', $vehicle->status?->name);
        }
    }
}
