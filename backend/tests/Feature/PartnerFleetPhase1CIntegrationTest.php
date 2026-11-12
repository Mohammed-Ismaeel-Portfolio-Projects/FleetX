<?php

namespace Tests\Feature;

use App\Actions\Assignment\CreateAssignmentAction;
use App\Actions\Shipment\CreateShipmentAction;
use App\Enums\CollaborationType;
use App\Enums\CompanyType;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerFleetPhase1CIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Company $clientCompany;
    private Company $partnerCompanyA;
    private Company $partnerCompanyB;
    private Status $draftShipmentStatus;
    private Status $pendingAssignmentStatus;
    private Status $activeVehicleStatus;
    private Status $activeDriverStatus;
    private Status $activeTrailerStatus;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientCompany = Company::factory()->create([
            'company_type' => CompanyType::CLIENT->value,
        ]);

        $this->partnerCompanyA = Company::factory()->create([
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        $this->partnerCompanyB = Company::factory()->create([
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        $this->draftShipmentStatus = Status::firstOrCreate(
            ['name' => 'Draft', 'type' => 'shipment'],
            ['code' => 'SHP_DRAFT', 'description' => 'Draft shipment']
        );

        $this->pendingAssignmentStatus = Status::firstOrCreate(
            ['name' => 'Pending', 'type' => 'assignment'],
            ['code' => 'ASG_PENDING', 'description' => 'Pending assignment']
        );

        $this->activeVehicleStatus = Status::firstOrCreate(
            ['name' => 'Active', 'type' => 'vehicle'],
            ['code' => 'VEH_ACTIVE', 'description' => 'Active vehicle']
        );

        $this->activeDriverStatus = Status::firstOrCreate(
            ['name' => 'Available', 'type' => 'driver'],
            ['code' => 'DRV_AVAILABLE', 'description' => 'Available driver']
        );

        $this->activeTrailerStatus = Status::firstOrCreate(
            ['name' => 'Available', 'type' => 'trailer'],
            ['code' => 'TRL_AVAILABLE', 'description' => 'Available trailer']
        );

        $this->adminUser = User::factory()->create();
    }

    private function createInternalVehicleDriverPair(): array
    {
        $driver = Driver::factory()->create([
            'ownership_type' => 'internal',
            'company_id' => null,
            'status_id' => $this->activeDriverStatus->id,
        ]);

        $vehicle = Vehicle::factory()->create([
            'ownership_type' => 'internal',
            'company_id' => null,
            'driver_id' => $driver->id,
            'status_id' => $this->activeVehicleStatus->id,
        ]);

        $trailer = Trailer::factory()->create([
            'ownership_type' => 'internal',
            'company_id' => null,
            'status_id' => $this->activeTrailerStatus->id,
        ]);

        return [$vehicle, $driver, $trailer];
    }

    private function createPartnerResources(Company $partnerCompany): array
    {
        $driver = Driver::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $partnerCompany->id,
            'status_id' => $this->activeDriverStatus->id,
        ]);

        $vehicle = Vehicle::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $partnerCompany->id,
            'driver_id' => null,
            'status_id' => $this->activeVehicleStatus->id,
        ]);

        $trailer = Trailer::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $partnerCompany->id,
            'status_id' => $this->activeTrailerStatus->id,
        ]);

        return [$vehicle, $driver, $trailer];
    }

    // 1. Internal shipment + internal resources => PASS
    public function test_internal_shipment_with_internal_resources_succeeds(): void
    {
        /** @var CreateShipmentAction $createShipmentAction */
        $createShipmentAction = app(CreateShipmentAction::class);
        $shipmentRes = $createShipmentAction->execute([
            'customer_company_id' => $this->clientCompany->id,
            'collaboration_type' => CollaborationType::INTERNAL->value,
            'priority' => 'medium',
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        $this->assertTrue($shipmentRes->success);
        /** @var Shipment $shipment */
        $shipment = $shipmentRes->data;

        [$vehicle, $driver, $trailer] = $this->createInternalVehicleDriverPair();

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $assignmentRes = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-INT-001',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($assignmentRes->success);
        $this->assertInstanceOf(Assignment::class, $assignmentRes->data);
    }

    // 2. Internal shipment + partner vehicle => FAIL
    public function test_internal_shipment_with_partner_vehicle_fails(): void
    {
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => null,
            'collaboration_type' => CollaborationType::INTERNAL->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        [$partnerVehicle, $partnerDriver] = $this->createPartnerResources($this->partnerCompanyA);

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-FAIL-001',
            'vehicle_id' => $partnerVehicle->id,
            'driver_id' => $partnerDriver->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($res->success);
        $this->assertEquals(422, $res->statusCode);
        $this->assertStringContainsString('Internally executed shipments must be assigned to FleetX internal fleet vehicles', $res->message);
    }

    // 3. Internal shipment + partner driver => FAIL
    public function test_internal_shipment_with_partner_driver_fails(): void
    {
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => null,
            'collaboration_type' => CollaborationType::INTERNAL->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        $unpairedInternalVehicle = Vehicle::factory()->create([
            'ownership_type' => 'internal',
            'company_id' => null,
            'driver_id' => null,
            'status_id' => $this->activeVehicleStatus->id,
        ]);
        [, $partnerDriver] = $this->createPartnerResources($this->partnerCompanyA);

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-FAIL-002',
            'vehicle_id' => $unpairedInternalVehicle->id,
            'driver_id' => $partnerDriver->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($res->success);
        $this->assertEquals(422, $res->statusCode);
        $this->assertStringContainsString('Internally executed shipments must be assigned to FleetX internal fleet drivers', $res->message);
    }

    // 4. Outbound partner shipment + matching partner resources => PASS
    public function test_outbound_partner_shipment_with_matching_partner_resources_succeeds(): void
    {
        /** @var CreateShipmentAction $createShipmentAction */
        $createShipmentAction = app(CreateShipmentAction::class);
        $shipmentRes = $createShipmentAction->execute([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => $this->partnerCompanyA->id,
            'collaboration_type' => CollaborationType::OUTBOUND_PARTNER->value,
            'priority' => 'high',
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        $this->assertTrue($shipmentRes->success);
        /** @var Shipment $shipment */
        $shipment = $shipmentRes->data;

        [$partnerVehicle, $partnerDriver, $partnerTrailer] = $this->createPartnerResources($this->partnerCompanyA);

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $assignmentRes = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-PRT-001',
            'vehicle_id' => $partnerVehicle->id,
            'driver_id' => $partnerDriver->id,
            'trailer_id' => $partnerTrailer->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($assignmentRes->success);
        $this->assertInstanceOf(Assignment::class, $assignmentRes->data);
    }

    // 5. Outbound partner shipment + wrong partner vehicle => FAIL
    public function test_outbound_partner_shipment_with_wrong_partner_vehicle_fails(): void
    {
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => $this->partnerCompanyA->id,
            'collaboration_type' => CollaborationType::OUTBOUND_PARTNER->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        [$wrongVehicle] = $this->createPartnerResources($this->partnerCompanyB);
        [, $matchingDriver] = $this->createPartnerResources($this->partnerCompanyA);

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-FAIL-005',
            'vehicle_id' => $wrongVehicle->id,
            'driver_id' => $matchingDriver->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($res->success);
        $this->assertEquals(422, $res->statusCode);
        $this->assertStringContainsString('Outbound partner shipments must be assigned to partner vehicles belonging to the executing partner company', $res->message);
    }

    // 6. Outbound partner shipment + wrong partner driver => FAIL
    public function test_outbound_partner_shipment_with_wrong_partner_driver_fails(): void
    {
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => $this->partnerCompanyA->id,
            'collaboration_type' => CollaborationType::OUTBOUND_PARTNER->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        [$matchingVehicle] = $this->createPartnerResources($this->partnerCompanyA);
        [, $wrongDriver] = $this->createPartnerResources($this->partnerCompanyB);

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-FAIL-006',
            'vehicle_id' => $matchingVehicle->id,
            'driver_id' => $wrongDriver->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($res->success);
        $this->assertEquals(422, $res->statusCode);
        $this->assertStringContainsString('Outbound partner shipments must be assigned to partner drivers belonging to the executing partner company', $res->message);
    }

    // 7. Outbound partner shipment + wrong partner trailer => FAIL
    public function test_outbound_partner_shipment_with_wrong_partner_trailer_fails(): void
    {
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => $this->partnerCompanyA->id,
            'collaboration_type' => CollaborationType::OUTBOUND_PARTNER->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        [$matchingVehicle, $matchingDriver] = $this->createPartnerResources($this->partnerCompanyA);
        [, , $wrongTrailer] = $this->createPartnerResources($this->partnerCompanyB);

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-FAIL-007',
            'vehicle_id' => $matchingVehicle->id,
            'driver_id' => $matchingDriver->id,
            'trailer_id' => $wrongTrailer->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($res->success);
        $this->assertEquals(422, $res->statusCode);
        $this->assertStringContainsString('Outbound partner shipments must be assigned to partner trailers belonging to the executing partner company', $res->message);
    }

    // 8. Outbound partner shipment + partner vehicle/driver with different permanent pairing => PASS
    public function test_outbound_partner_shipment_dynamic_pairing_succeeds(): void
    {
        $otherDriver = Driver::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $this->partnerCompanyA->id,
            'status_id' => $this->activeDriverStatus->id,
        ]);

        $vehicle = Vehicle::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $this->partnerCompanyA->id,
            'driver_id' => $otherDriver->id, // Permanent pairing with another driver
            'status_id' => $this->activeVehicleStatus->id,
        ]);

        $unpairedDriver = Driver::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $this->partnerCompanyA->id,
            'status_id' => $this->activeDriverStatus->id,
        ]);

        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => $this->partnerCompanyA->id,
            'collaboration_type' => CollaborationType::OUTBOUND_PARTNER->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-DYNAMIC-001',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $unpairedDriver->id, // Dynamic driver assignment allowed for partner resources!
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($res->success);
        $this->assertInstanceOf(Assignment::class, $res->data);
    }

    // 9. Inbound partner shipment + FleetX internal resources => PASS
    public function test_inbound_partner_shipment_with_internal_resources_succeeds(): void
    {
        /** @var CreateShipmentAction $createShipmentAction */
        $createShipmentAction = app(CreateShipmentAction::class);
        $shipmentRes = $createShipmentAction->execute([
            'customer_company_id' => $this->partnerCompanyA->id,
            'executing_company_id' => null, // Executed internally by FleetX
            'collaboration_type' => CollaborationType::INBOUND_PARTNER->value,
            'priority' => 'medium',
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        $this->assertTrue($shipmentRes->success);
        /** @var Shipment $shipment */
        $shipment = $shipmentRes->data;

        [$vehicle, $driver, $trailer] = $this->createInternalVehicleDriverPair();

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $assignmentRes = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-INBOUND-001',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($assignmentRes->success);
    }

    // 10. Inbound partner shipment + partner resources => FAIL
    public function test_inbound_partner_shipment_with_partner_resources_fails(): void
    {
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->partnerCompanyA->id,
            'executing_company_id' => null, // Internal FleetX execution
            'collaboration_type' => CollaborationType::INBOUND_PARTNER->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        [$partnerVehicle, $partnerDriver] = $this->createPartnerResources($this->partnerCompanyA);

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-FAIL-010',
            'vehicle_id' => $partnerVehicle->id,
            'driver_id' => $partnerDriver->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertFalse($res->success);
        $this->assertEquals(422, $res->statusCode);
        $this->assertStringContainsString('Internally executed shipments must be assigned to FleetX internal fleet vehicles', $res->message);
    }

    // 11. Existing single-active-assignment constraints still work
    public function test_single_active_assignment_constraints_work(): void
    {
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => null,
            'collaboration_type' => CollaborationType::INTERNAL->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        [$vehicle, $driver, $trailer] = $this->createInternalVehicleDriverPair();

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res1 = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-CONSTR-001',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);
        $this->assertTrue($res1->success);

        // Attempting to assign same shipment again => FAIL
        $res2 = $createAssignmentAction->execute([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASG-CONSTR-002',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);
        $this->assertFalse($res2->success);
        $this->assertEquals(422, $res2->statusCode);
        $this->assertStringContainsString('Shipment already has an active assignment', $res2->message);
    }

    // 12. Legacy shipment with NULL executing_company_id remains treated as internal
    public function test_legacy_shipment_with_null_executing_company_treated_as_internal(): void
    {
        $legacyShipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => null,
            'collaboration_type' => CollaborationType::INTERNAL->value,
            'status_id' => $this->draftShipmentStatus->id,
        ]);

        $this->assertTrue($legacyShipment->isInternallyExecuted());
        $this->assertFalse($legacyShipment->isPartnerExecuted());

        [$vehicle, $driver] = $this->createInternalVehicleDriverPair();

        /** @var CreateAssignmentAction $createAssignmentAction */
        $createAssignmentAction = app(CreateAssignmentAction::class);
        $res = $createAssignmentAction->execute([
            'shipment_id' => $legacyShipment->id,
            'assignment_code' => 'ASG-LEGACY-001',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_by' => $this->adminUser->id,
            'status_id' => $this->pendingAssignmentStatus->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($res->success);
    }
}
