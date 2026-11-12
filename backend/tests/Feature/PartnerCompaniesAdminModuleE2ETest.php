<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerCompaniesAdminModuleE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        if (Role::count() === 0) {
            $this->seed(\Database\Seeders\RolesSeeder::class);
        }

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $clientRole = Role::where('name', 'client')->firstOrFail();

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->clientUser = User::factory()->create([
            'role_id' => $clientRole->id,
        ]);
    }

    /**
     * Phase 2A & 2B: Partner listing and client company exclusion.
     */
    public function test_partner_listing_returns_only_partners_and_excludes_clients(): void
    {
        $clientCompany = Company::factory()->create([
            'company_name' => 'B2B Client Logistics Corp',
            'company_type' => CompanyType::CLIENT->value,
            'is_active' => true,
        ]);

        $partnerCarrier = Company::factory()->create([
            'company_name' => 'Trans-National Partner Lines',
            'company_type' => CompanyType::PARTNER_CARRIER->value,
            'is_active' => true,
        ]);

        $hybridCarrier = Company::factory()->create([
            'company_name' => 'Hybrid Express Partner',
            'company_type' => CompanyType::HYBRID->value,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/v1/companies?company_type=partner_carrier');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $companyNames = collect($response->json('data.data'))->pluck('company_name')->all();

        $this->assertContains('Trans-National Partner Lines', $companyNames);
        $this->assertContains('Hybrid Express Partner', $companyNames);
        $this->assertNotContains('B2B Client Logistics Corp', $companyNames);
    }

    /**
     * Phase 2C-G: Full Partner Company CRUD Lifecycle (Create, Update, Toggle Status, Soft Delete, Restore).
     */
    public function test_full_partner_company_crud_lifecycle(): void
    {
        // 1. Create Partner Carrier
        $createData = [
            'company_name' => 'Apex Partner Express LLC',
            'legal_name' => 'Apex Partner Express LLC Legal',
            'company_code' => 'APX-PARTNER-01',
            'company_type' => 'partner_carrier',
            'primary_email' => 'contact@apexpartner.example.com',
            'primary_phone' => '+15550192834',
            'country' => 'US',
            'city' => 'Chicago',
            'address_line_1' => '100 Partner Way',
            'postal_code' => '60606',
            'currency_code' => 'USD',
            'is_active' => true,
        ];

        $createRes = $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/companies', $createData);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'Apex Partner Express LLC')
            ->assertJsonPath('data.company_type', 'partner_carrier');

        $partnerId = $createRes->json('data.id');
        $this->assertDatabaseHas('companies', [
            'id' => $partnerId,
            'company_type' => 'partner_carrier',
            'is_active' => true,
        ]);

        // 2. Update Partner Carrier
        $updateData = [
            'company_name' => 'Apex Partner Global Logistics',
            'legal_name' => 'Apex Partner Express LLC Legal',
            'primary_email' => 'updated@apexpartner.example.com',
        ];

        $updateRes = $this->actingAs($this->adminUser, 'api')
            ->putJson("/api/v1/companies/{$partnerId}", $updateData);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.company_name', 'Apex Partner Global Logistics')
            ->assertJsonPath('data.primary_email', 'updated@apexpartner.example.com');

        // 3. Deactivate Partner Carrier
        $statusRes = $this->actingAs($this->adminUser, 'api')
            ->patchJson("/api/v1/companies/{$partnerId}/status", ['is_active' => false]);

        $statusRes->assertStatus(200);
        $this->assertDatabaseHas('companies', [
            'id' => $partnerId,
            'is_active' => false,
        ]);

        // Reactivate
        $reactivateRes = $this->actingAs($this->adminUser, 'api')
            ->patchJson("/api/v1/companies/{$partnerId}/status", ['is_active' => true]);
        $reactivateRes->assertStatus(200);

        // 4. Soft Delete Partner Carrier
        $deleteRes = $this->actingAs($this->adminUser, 'api')
            ->deleteJson("/api/v1/companies/{$partnerId}");

        $deleteRes->assertStatus(200);
        $this->assertSoftDeleted('companies', ['id' => $partnerId]);

        // 5. Restore Partner Carrier
        $restoreRes = $this->actingAs($this->adminUser, 'api')
            ->patchJson("/api/v1/companies/{$partnerId}/restore");

        $restoreRes->assertStatus(200);
        $this->assertDatabaseHas('companies', [
            'id' => $partnerId,
            'deleted_at' => null,
        ]);
    }

    /**
     * Phase 3: Security & Authorization Enforcement.
     */
    public function test_non_admin_cannot_manage_partner_companies(): void
    {
        $partnerCompany = Company::factory()->create([
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        // Non-admin Store attempt -> 403
        $this->actingAs($this->clientUser, 'api')
            ->postJson('/api/v1/companies', [
                'company_name' => 'Hacker Carrier Inc',
                'legal_name' => 'Hacker Carrier Inc Legal',
                'company_code' => 'HCK-01',
                'primary_email' => 'hacker@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address_line_1' => '123 Fake St',
                'postal_code' => '10001',
                'currency_code' => 'USD',
            ])->assertStatus(403);

        // Non-admin Update attempt -> 403
        $this->actingAs($this->clientUser, 'api')
            ->putJson("/api/v1/companies/{$partnerCompany->id}", [
                'company_name' => 'Unauthorized Change',
            ])->assertStatus(403);

        // Non-admin Toggle Status attempt -> 403
        $this->actingAs($this->clientUser, 'api')
            ->patchJson("/api/v1/companies/{$partnerCompany->id}/status", [
                'is_active' => false,
            ])->assertStatus(403);

        // Non-admin Delete attempt -> 403
        $this->actingAs($this->clientUser, 'api')
            ->deleteJson("/api/v1/companies/{$partnerCompany->id}")
            ->assertStatus(403);

        // Non-admin Restore attempt -> 403
        $this->actingAs($this->clientUser, 'api')
            ->patchJson("/api/v1/companies/{$partnerCompany->id}/restore")
            ->assertStatus(403);
    }

    /**
     * Phase 4: Partner Fleet Resource Scoping & Isolation (Partner A vs Partner B).
     */
    public function test_partner_fleet_resource_scoping_isolation(): void
    {
        $partnerA = Company::factory()->create([
            'company_name' => 'Carrier Partner Alpha',
            'company_code' => 'ALPHA-001',
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        $partnerB = Company::factory()->create([
            'company_name' => 'Carrier Partner Beta',
            'company_code' => 'BETA-001',
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        // Partner A Fleet Resources
        $vehicleA = Vehicle::factory()->create([
            'company_id' => $partnerA->id,
            'operational_code' => 'VEH-ALPHA-01',
        ]);
        $driverA = Driver::factory()->create([
            'company_id' => $partnerA->id,
            'first_name' => 'DriverAlpha',
        ]);
        $trailerA = Trailer::factory()->create([
            'company_id' => $partnerA->id,
            'trailer_code' => 'TRL-ALPHA-01',
        ]);
        $shipmentA = Shipment::factory()->create([
            'executing_company_id' => $partnerA->id,
            'shipment_number' => 'SHP-ALPHA-01',
        ]);

        // Partner B Fleet Resources
        $vehicleB = Vehicle::factory()->create([
            'company_id' => $partnerB->id,
            'operational_code' => 'VEH-BETA-01',
        ]);
        $driverB = Driver::factory()->create([
            'company_id' => $partnerB->id,
            'first_name' => 'DriverBeta',
        ]);
        $trailerB = Trailer::factory()->create([
            'company_id' => $partnerB->id,
            'trailer_code' => 'TRL-BETA-01',
        ]);
        $shipmentB = Shipment::factory()->create([
            'executing_company_id' => $partnerB->id,
            'shipment_number' => 'SHP-BETA-01',
        ]);

        // 1. Test Vehicles query scoped to Partner A
        $vRes = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/vehicles?company_id={$partnerA->id}");
        $vRes->assertStatus(200);
        $vCodes = collect($vRes->json('data.data'))->pluck('operational_code')->all();
        $this->assertContains('VEH-ALPHA-01', $vCodes);
        $this->assertNotContains('VEH-BETA-01', $vCodes);

        // 2. Test Drivers query scoped to Partner A
        $dRes = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/drivers?company_id={$partnerA->id}");
        $dRes->assertStatus(200);
        $dNames = collect($dRes->json('data.data'))->pluck('first_name')->all();
        $this->assertContains('DriverAlpha', $dNames);
        $this->assertNotContains('DriverBeta', $dNames);

        // 3. Test Trailers query scoped to Partner A
        $tRes = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/trailers?company_id={$partnerA->id}");
        $tRes->assertStatus(200);
        $tCodes = collect($tRes->json('data.data'))->pluck('trailer_code')->all();
        $this->assertContains('TRL-ALPHA-01', $tCodes);
        $this->assertNotContains('TRL-BETA-01', $tCodes);

        // 4. Test Executed Shipments query scoped to Partner A
        $sRes = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/shipments?executing_company_id={$partnerA->id}");
        $sRes->assertStatus(200);
        $sNumbers = collect($sRes->json('data.data'))->pluck('shipment_number')->all();
        $this->assertContains('SHP-ALPHA-01', $sNumbers);
        $this->assertNotContains('SHP-BETA-01', $sNumbers);
    }

    /**
     * Phase 6: Client vs Partner Company domain separation.
     */
    public function test_client_versus_partner_domain_separation(): void
    {
        $clientCompany = Company::factory()->create([
            'company_name' => 'B2B Client Logistics Corp',
            'company_type' => CompanyType::CLIENT->value,
        ]);

        $partnerCarrier = Company::factory()->create([
            'company_name' => 'Trans-National Partner Lines',
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        // Query Client Companies
        $clientRes = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/v1/companies?company_type=client');

        $clientNames = collect($clientRes->json('data.data'))->pluck('company_name')->all();
        $this->assertContains('B2B Client Logistics Corp', $clientNames);
        $this->assertNotContains('Trans-National Partner Lines', $clientNames);

        // Query Partner Companies
        $partnerRes = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/v1/companies?company_type=partner_carrier');

        $partnerNames = collect($partnerRes->json('data.data'))->pluck('company_name')->all();
        $this->assertContains('Trans-National Partner Lines', $partnerNames);
        $this->assertNotContains('B2B Client Logistics Corp', $partnerNames);
    }
}
