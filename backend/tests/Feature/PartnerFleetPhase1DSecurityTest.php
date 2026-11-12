<?php

namespace Tests\Feature;

use App\Enums\CollaborationType;
use App\Enums\CompanyType;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingState;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class PartnerFleetPhase1DSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $dispatcherUser;
    private Company $partnerCompanyA;
    private Company $partnerCompanyB;
    private Company $clientCompany;
    private User $partnerADispatcher;
    private User $partnerBDispatcher;
    private User $clientUser;
    private User $partnerADriverUser;
    private User $partnerBDriverUser;
    private Driver $partnerADriver;
    private Driver $partnerBDriver;
    private Vehicle $partnerAVehicle;
    private Vehicle $partnerBVehicle;
    private Shipment $internalShipment;
    private Shipment $outboundPartnerAShipment;
    private Shipment $outboundPartnerBShipment;
    private Shipment $inboundPartnerAShipment;
    private Assignment $partnerAAssignment;
    private Assignment $partnerBAssignment;
    private Status $assignedStatus;
    private Status $inTransitStatus;
    private Status $draftStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $dispatcherRole = Role::firstOrCreate(['name' => 'dispatcher']);
        $driverRole = Role::firstOrCreate(['name' => 'driver']);
        $clientRole = Role::firstOrCreate(['name' => 'client']);

        $internalCompany = Company::factory()->create([
            'company_type' => CompanyType::INTERNAL,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
            'company_id' => $internalCompany->id,
        ]);

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'company_id' => $internalCompany->id,
        ]);

        $this->partnerCompanyA = Company::factory()->create([
            'company_name' => 'Partner Carrier A',
            'company_type' => CompanyType::PARTNER_CARRIER,
            'is_active' => true,
        ]);

        $this->partnerCompanyB = Company::factory()->create([
            'company_name' => 'Partner Carrier B',
            'company_type' => CompanyType::PARTNER_CARRIER,
            'is_active' => true,
        ]);

        $this->clientCompany = Company::factory()->create([
            'company_name' => 'Client Company A',
            'company_type' => CompanyType::CLIENT,
            'is_active' => true,
        ]);

        $this->partnerADispatcher = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'company_id' => $this->partnerCompanyA->id,
        ]);

        $this->partnerBDispatcher = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'company_id' => $this->partnerCompanyB->id,
        ]);

        $this->clientUser = User::factory()->create([
            'role_id' => $clientRole->id,
            'company_id' => $this->clientCompany->id,
        ]);

        $this->partnerADriverUser = User::factory()->create([
            'role_id' => $driverRole->id,
            'company_id' => $this->partnerCompanyA->id,
        ]);

        $this->partnerADriver = Driver::factory()->create([
            'company_id' => $this->partnerCompanyA->id,
            'ownership_type' => 'partner',
            'user_id' => $this->partnerADriverUser->id,
        ]);

        $this->partnerBDriverUser = User::factory()->create([
            'role_id' => $driverRole->id,
            'company_id' => $this->partnerCompanyB->id,
        ]);

        $this->partnerBDriver = Driver::factory()->create([
            'company_id' => $this->partnerCompanyB->id,
            'ownership_type' => 'partner',
            'user_id' => $this->partnerBDriverUser->id,
        ]);

        $this->partnerAVehicle = Vehicle::factory()->create([
            'company_id' => $this->partnerCompanyA->id,
            'ownership_type' => 'partner',
        ]);

        $this->partnerBVehicle = Vehicle::factory()->create([
            'company_id' => $this->partnerCompanyB->id,
            'ownership_type' => 'partner',
        ]);

        $this->draftStatus = Status::firstOrCreate(
            ['name' => 'Draft', 'type' => 'shipment'],
            ['color' => '#CCCCCC', 'sort_order' => 1]
        );

        $this->assignedStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'shipment'],
            ['color' => '#0000FF', 'sort_order' => 2]
        );

        $this->inTransitStatus = Status::firstOrCreate(
            ['name' => 'In Transit', 'type' => 'shipment'],
            ['color' => '#00FF00', 'sort_order' => 3]
        );

        $assignmentAssignedStatus = Status::firstOrCreate(
            ['name' => 'Assigned', 'type' => 'assignment'],
            ['color' => '#0000FF', 'sort_order' => 1]
        );

        $assignmentInOpStatus = Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['color' => '#00FF00', 'sort_order' => 2]
        );

        $this->internalShipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => null,
            'collaboration_type' => CollaborationType::INTERNAL,
            'status_id' => $this->assignedStatus->id,
        ]);

        $this->outboundPartnerAShipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => $this->partnerCompanyA->id,
            'collaboration_type' => CollaborationType::OUTBOUND_PARTNER,
            'status_id' => $this->assignedStatus->id,
        ]);

        $this->outboundPartnerBShipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => $this->partnerCompanyB->id,
            'collaboration_type' => CollaborationType::OUTBOUND_PARTNER,
            'status_id' => $this->assignedStatus->id,
        ]);

        $this->inboundPartnerAShipment = Shipment::factory()->create([
            'customer_company_id' => $this->partnerCompanyA->id,
            'executing_company_id' => null,
            'collaboration_type' => CollaborationType::INBOUND_PARTNER,
            'status_id' => $this->assignedStatus->id,
        ]);

        $this->partnerAAssignment = Assignment::factory()->create([
            'shipment_id' => $this->outboundPartnerAShipment->id,
            'vehicle_id' => $this->partnerAVehicle->id,
            'driver_id' => $this->partnerADriver->id,
            'trailer_id' => null,
            'status_id' => $assignmentInOpStatus->id,
            'is_active' => true,
        ]);

        $this->partnerBAssignment = Assignment::factory()->create([
            'shipment_id' => $this->outboundPartnerBShipment->id,
            'vehicle_id' => $this->partnerBVehicle->id,
            'driver_id' => $this->partnerBDriver->id,
            'trailer_id' => null,
            'status_id' => $assignmentInOpStatus->id,
            'is_active' => true,
        ]);
    }

    // 1. FleetX admin -> tracking.fleet => PASS
    public function test_fleetx_admin_can_access_fleet_tracking_channel(): void
    {
        $response = $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-tracking.fleet',
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200);
    }

    // 2. FleetX dispatcher -> tracking.fleet => PASS
    public function test_fleetx_dispatcher_can_access_fleet_tracking_channel(): void
    {
        $response = $this->actingAs($this->dispatcherUser, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-tracking.fleet',
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200);
    }

    // 3. Partner dispatcher -> tracking.fleet => FAIL
    public function test_partner_dispatcher_cannot_access_fleet_tracking_channel(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-tracking.fleet',
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    // 4. Partner driver -> tracking.fleet => FAIL
    public function test_partner_driver_cannot_access_fleet_tracking_channel(): void
    {
        $response = $this->actingAs($this->partnerADriverUser, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-tracking.fleet',
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    // 5. Client user -> tracking.fleet => FAIL
    public function test_client_user_cannot_access_fleet_tracking_channel(): void
    {
        $response = $this->actingAs($this->clientUser, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => 'private-tracking.fleet',
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    // 6. Partner A dispatcher -> Partner A shipment channel => PASS
    public function test_partner_a_dispatcher_can_access_own_shipment_channel(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => "private-tracking.shipment.{$this->outboundPartnerAShipment->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200);
    }

    // 7. Partner A dispatcher -> Partner B shipment channel => FAIL
    public function test_partner_a_dispatcher_cannot_access_partner_b_shipment_channel(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => "private-tracking.shipment.{$this->outboundPartnerBShipment->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    // 8. Partner dispatcher -> FleetX internal shipment channel => FAIL
    public function test_partner_dispatcher_cannot_access_internal_shipment_channel(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->postJson('/api/v1/broadcasting/auth', [
                'channel_name' => "private-tracking.shipment.{$this->internalShipment->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    // 9. Inbound Partner A client -> own shipment tracking REST => PASS
    public function test_inbound_partner_a_client_can_get_own_shipment_tracking(): void
    {
        $partnerAClientUser = User::factory()->create([
            'role_id' => Role::where('name', 'client')->first()->id,
            'company_id' => $this->partnerCompanyA->id,
        ]);

        $response = $this->actingAs($partnerAClientUser, 'api')
            ->getJson("/api/v1/client/shipments/{$this->inboundPartnerAShipment->id}/tracking");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // 10. Inbound Partner A client -> Partner B shipment => FAIL
    public function test_inbound_partner_a_client_cannot_get_partner_b_shipment_tracking(): void
    {
        $partnerAClientUser = User::factory()->create([
            'role_id' => Role::where('name', 'client')->first()->id,
            'company_id' => $this->partnerCompanyA->id,
        ]);

        $response = $this->actingAs($partnerAClientUser, 'api')
            ->getJson("/api/v1/client/shipments/{$this->outboundPartnerBShipment->id}/tracking");

        $response->assertStatus(404);
    }

    // 11. Inbound Partner A client -> FleetX unrelated shipment => FAIL
    public function test_inbound_partner_a_client_cannot_get_unrelated_internal_shipment_tracking(): void
    {
        $partnerAClientUser = User::factory()->create([
            'role_id' => Role::where('name', 'client')->first()->id,
            'company_id' => $this->partnerCompanyA->id,
        ]);

        $response = $this->actingAs($partnerAClientUser, 'api')
            ->getJson("/api/v1/client/shipments/{$this->internalShipment->id}/tracking");

        $response->assertStatus(404);
    }

    // 12. Outbound Partner A dispatcher -> own executing shipment => PASS
    public function test_outbound_partner_a_dispatcher_can_show_own_executing_shipment(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->getJson("/api/v1/shipments/{$this->outboundPartnerAShipment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // 13. Outbound Partner A dispatcher -> Partner B executing shipment => FAIL
    public function test_outbound_partner_a_dispatcher_cannot_show_partner_b_executing_shipment(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->getJson("/api/v1/shipments/{$this->outboundPartnerBShipment->id}");

        $response->assertStatus(403);
    }

    // 14. Partner driver -> own active assignment => PASS
    public function test_partner_driver_can_get_own_active_assignment(): void
    {
        $response = $this->actingAs($this->partnerADriverUser, 'api')
            ->getJson("/api/v1/driver/assignments/{$this->partnerAAssignment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // 15. Partner driver -> another driver's assignment => FAIL
    public function test_partner_driver_cannot_get_another_drivers_assignment(): void
    {
        $response = $this->actingAs($this->partnerADriverUser, 'api')
            ->getJson("/api/v1/driver/assignments/{$this->partnerBAssignment->id}");

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }

    // 16. Partner driver -> submit telemetry for own assignment => PASS
    public function test_partner_driver_can_submit_telemetry_for_own_assignment(): void
    {
        $response = $this->actingAs($this->partnerADriverUser, 'api')
            ->postJson("/api/v1/driver/assignments/{$this->partnerAAssignment->id}/tracking", [
                'latitude' => 41.0082,
                'longitude' => 28.9784,
                'speed' => 60,
                'heading' => 180,
                'accuracy' => 5,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // 17. Partner driver -> submit telemetry for another assignment => FAIL
    public function test_partner_driver_cannot_submit_telemetry_for_another_assignment(): void
    {
        $response = $this->actingAs($this->partnerADriverUser, 'api')
            ->postJson("/api/v1/driver/assignments/{$this->partnerBAssignment->id}/tracking", [
                'latitude' => 41.0082,
                'longitude' => 28.9784,
                'speed' => 60,
                'heading' => 180,
                'accuracy' => 5,
                'recorded_at' => now()->toDateTimeString(),
            ]);

        $response->assertStatus(404);
    }

    // 18. Partner user -> internal fleet KPI endpoint => FAIL
    public function test_partner_user_cannot_access_internal_fleet_kpi_endpoint(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->getJson('/api/v1/vehicles/fleet-kpis');

        $response->assertStatus(403);
    }

    // 19. Partner user -> internal fleet management endpoint => FAIL
    public function test_partner_user_cannot_access_other_company_vehicle(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->getJson("/api/v1/vehicles/{$this->partnerBVehicle->id}");

        $response->assertStatus(403);
    }

    // 20. User with correct role but wrong company => FAIL
    public function test_user_with_correct_role_but_wrong_company_is_denied(): void
    {
        $response = $this->actingAs($this->partnerADispatcher, 'api')
            ->getJson("/api/v1/shipments/{$this->outboundPartnerBShipment->id}");

        $response->assertStatus(403);
    }

    // 21. Inactive partner company user => FAIL
    public function test_inactive_partner_company_user_is_denied(): void
    {
        $inactivePartnerCompany = Company::factory()->create([
            'company_name' => 'Inactive Partner',
            'company_type' => CompanyType::PARTNER_CARRIER,
            'is_active' => false,
        ]);

        $inactivePartnerDispatcher = User::factory()->create([
            'role_id' => Role::where('name', 'dispatcher')->first()->id,
            'company_id' => $inactivePartnerCompany->id,
        ]);

        $inactiveShipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'executing_company_id' => $inactivePartnerCompany->id,
            'collaboration_type' => CollaborationType::OUTBOUND_PARTNER,
            'status_id' => $this->assignedStatus->id,
        ]);

        $response = $this->actingAs($inactivePartnerDispatcher, 'api')
            ->getJson("/api/v1/shipments/{$inactiveShipment->id}");

        $response->assertStatus(403);
    }

    // IDOR Negative Test: Partner A client requesting Partner B shipment details
    public function test_idor_client_shipment_tracking_does_not_leak_other_company_data(): void
    {
        $partnerAClientUser = User::factory()->create([
            'role_id' => Role::where('name', 'client')->first()->id,
            'company_id' => $this->partnerCompanyA->id,
        ]);

        $response = $this->actingAs($partnerAClientUser, 'api')
            ->getJson("/api/v1/client/shipments/{$this->outboundPartnerBShipment->id}/tracking");

        $response->assertStatus(404)
            ->assertJsonMissing(['shipment_number'])
            ->assertJsonMissing(['latitude'])
            ->assertJsonMissing(['driver']);
    }
}
