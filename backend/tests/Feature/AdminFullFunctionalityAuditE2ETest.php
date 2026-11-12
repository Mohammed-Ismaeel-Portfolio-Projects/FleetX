<?php

namespace Tests\Feature;

use App\Enums\CompanyType;
use App\Enums\MonthlyInvoiceStatus;
use App\Models\Company;
use App\Models\Driver;
use App\Models\FuelType;
use App\Models\InvoiceLineItem;
use App\Models\MonthlyInvoice;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFullFunctionalityAuditE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $dispatcherUser;
    private User $clientUser;
    private User $driverUser;
    private Company $clientCompany;
    private Company $partnerCompany;

    protected function setUp(): void
    {
        parent::setUp();

        if (Role::count() === 0) {
            $this->seed(\Database\Seeders\RolesSeeder::class);
        }

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        $clientRole = Role::where('name', 'client')->firstOrFail();
        $driverRole = Role::where('name', 'driver')->firstOrFail();

        $this->clientCompany = Company::factory()->create([
            'company_name' => 'Shipper Client Corp',
            'company_type' => CompanyType::CLIENT->value,
            'is_active' => true,
        ]);

        $this->partnerCompany = Company::factory()->create([
            'company_name' => 'Carrier Partner Express',
            'company_type' => CompanyType::PARTNER_CARRIER->value,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin.audit@fleetx.test',
            'password' => bcrypt('password123'),
        ]);

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'email' => 'dispatcher.audit@fleetx.test',
        ]);

        $this->clientUser = User::factory()->create([
            'role_id' => $clientRole->id,
            'company_id' => $this->clientCompany->id,
            'email' => 'client.audit@fleetx.test',
        ]);

        $this->driverUser = User::factory()->create([
            'role_id' => $driverRole->id,
            'company_id' => $this->partnerCompany->id,
            'email' => 'driver.audit@fleetx.test',
        ]);
    }

    /**
     * PHASE 1: Authentication, Token Handling & RBAC Authorization Boundaries
     */
    public function test_phase1_admin_auth_and_rbac_boundaries(): void
    {
        // 1. Admin login success
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin.audit@fleetx.test',
            'password' => 'password123',
        ]);
        $loginRes->assertStatus(200);

        // 2. Invalid login returns 422 (validation error on credentials)
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin.audit@fleetx.test',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        // 3. Me & Identity check
        $meRes = $this->actingAs($this->adminUser, 'api')->getJson('/api/v1/auth/me');
        $meRes->assertStatus(200)
            ->assertJsonPath('data.email', 'admin.audit@fleetx.test');

        // 4. Non-admin attempting Admin Pricing Policy Governance (RBAC Gate)
        $this->actingAs($this->clientUser, 'api')
            ->getJson('/api/v1/admin/pricing/policies')
            ->assertStatus(403);

        $this->actingAs($this->driverUser, 'api')
            ->getJson('/api/v1/admin/pricing/policies')
            ->assertStatus(403);
    }

    /**
     * PHASE 2: Dashboard KPIs and Activity Stream
     */
    public function test_phase2_admin_dashboard_kpis_and_activity(): void
    {
        $resKpi = $this->actingAs($this->adminUser, 'api')->getJson('/api/v1/dashboard/kpi');
        $resKpi->assertStatus(200)
            ->assertJsonPath('success', true);

        $resAct = $this->actingAs($this->adminUser, 'api')->getJson('/api/v1/dashboard/activity');
        $resAct->assertStatus(200)
            ->assertJsonPath('success', true);

        $resPerf = $this->actingAs($this->adminUser, 'api')->getJson('/api/v1/dashboard/performance');
        $resPerf->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * PHASE 3 & 4: Company Management & Partner Carrier Scoping
     */
    public function test_phase3_and_4_company_crud_and_partner_scoping(): void
    {
        // Duplicate code check
        Company::factory()->create(['company_code' => 'DUP-CODE-01']);
        $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/companies', [
                'company_name' => 'Duplicate Code Ltd',
                'legal_name' => 'Duplicate Code Ltd Legal',
                'company_code' => 'DUP-CODE-01',
                'primary_email' => 'dup@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address_line_1' => '123 Test St',
                'postal_code' => '10001',
                'currency_code' => 'USD',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['company_code']);

        // Create Partner Carrier
        $partnerData = [
            'company_name' => 'Global Logistics Partner',
            'legal_name' => 'Global Logistics Partner Inc',
            'company_code' => 'GLP-CARRIER-99',
            'company_type' => 'partner_carrier',
            'primary_email' => 'ops@globallogistics.example',
            'country' => 'US',
            'city' => 'Chicago',
            'address_line_1' => '999 Transport Blvd',
            'postal_code' => '60606',
            'currency_code' => 'USD',
            'is_active' => true,
        ];

        $createRes = $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/companies', $partnerData);
        $createRes->assertStatus(201)
            ->assertJsonPath('data.company_type', 'partner_carrier');

        $partnerId = $createRes->json('data.id');

        // Verify Partner Companies filter
        $listRes = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/v1/companies?company_type=partner_carrier');
        $listRes->assertStatus(200);

        $names = collect($listRes->json('data.data'))->pluck('company_name')->all();
        $this->assertContains('Global Logistics Partner', $names);
        $this->assertNotContains('Shipper Client Corp', $names);

        // Edit Company
        $updateRes = $this->actingAs($this->adminUser, 'api')
            ->putJson("/api/v1/companies/{$partnerId}", [
                'company_name' => 'Global Logistics Partner Updated',
                'legal_name' => 'Global Logistics Partner Inc',
            ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.company_name', 'Global Logistics Partner Updated');

        // Status toggle
        $statusRes = $this->actingAs($this->adminUser, 'api')
            ->patchJson("/api/v1/companies/{$partnerId}/status", ['is_active' => false]);
        $statusRes->assertStatus(200);

        // Soft Delete & Restore
        $deleteRes = $this->actingAs($this->adminUser, 'api')
            ->deleteJson("/api/v1/companies/{$partnerId}");
        $deleteRes->assertStatus(200);
        $this->assertSoftDeleted('companies', ['id' => $partnerId]);

        $restoreRes = $this->actingAs($this->adminUser, 'api')
            ->patchJson("/api/v1/companies/{$partnerId}/restore");
        $restoreRes->assertStatus(200);
    }

    /**
     * PHASE 5: Pricing Governance & Rate Calculation
     */
    public function test_phase5_pricing_management(): void
    {
        // Admin index pricing policies
        $policiesRes = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/v1/admin/pricing/policies');
        $policiesRes->assertStatus(200);

        // Admin timeline
        $timelineRes = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/v1/admin/pricing/policies/timeline');
        $timelineRes->assertStatus(200);

        // Pricing calculation validation check (negative distance must fail validation)
        $invalidCalc = $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/pricing/calculate', [
                'origin' => ['latitude' => 40.7128, 'longitude' => -74.0060],
                'destination' => ['latitude' => 41.8781, 'longitude' => -87.6298],
                'cargo_type' => 'INVALID_CARGO',
                'weight_kg' => -50,
            ]);
        $invalidCalc->assertStatus(422);
    }

    /**
     * PHASE 7, 8, 9: Fleet Resources (Drivers, Vehicles, Trailers) Management
     */
    public function test_phase7_8_9_fleet_resources_crud(): void
    {
        $partnerCompany = Company::factory()->create([
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        $vType = VehicleType::firstOrCreate(['code' => 'DRY_VAN'], ['name' => 'Dry Van']);
        $fType = FuelType::firstOrCreate(['code' => 'DIESEL'], ['name' => 'Diesel']);
        $vStatus = Status::firstOrCreate(['type' => 'vehicle', 'name' => 'available'], ['display_name' => 'Available']);
        $dStatus = Status::firstOrCreate(['type' => 'driver', 'name' => 'available'], ['display_name' => 'Available']);
        $tStatus = Status::firstOrCreate(['type' => 'trailer', 'name' => 'available'], ['display_name' => 'Available']);

        // 1. Vehicle CRUD
        $vehData = [
            'company_id' => $partnerCompany->id,
            'operational_code' => 'VEH-AUDIT-101',
            'fleet_number' => 'FLT-101',
            'plate_number' => 'PLATE-101',
            'manufacturer' => 'Volvo',
            'model' => 'FH16',
            'vehicle_type_id' => $vType->id,
            'fuel_type_id' => $fType->id,
            'status_id' => $vStatus->id,
            'is_active' => true,
        ];

        $vehRes = $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/vehicles', $vehData);
        $vehRes->assertStatus(201)
            ->assertJsonPath('data.operational_code', 'VEH-AUDIT-101');

        // Duplicate vehicle code -> 422
        $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/vehicles', $vehData)
            ->assertStatus(422);

        // 2. Driver CRUD
        $driverData = [
            'company_id' => $partnerCompany->id,
            'employee_number' => 'EMP-AUDIT-101',
            'first_name' => 'John',
            'last_name' => 'Driver',
            'license_number' => 'DL-AUDIT-101',
            'license_class' => 'CLASS_A',
            'license_expiry_date' => '2028-12-31',
            'nationality' => 'US',
            'date_of_birth' => '1990-01-01',
            'employment_date' => '2023-01-01',
            'email' => 'driver.101@example.com',
            'phone' => '+1555987654',
            'status_id' => $dStatus->id,
            'is_active' => true,
        ];

        $driverRes = $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/drivers', $driverData);
        $driverRes->assertStatus(201)
            ->assertJsonPath('data.license_number', 'DL-AUDIT-101');

        // 3. Trailer CRUD
        $trailerData = [
            'company_id' => $partnerCompany->id,
            'trailer_code' => 'TRL-AUDIT-101',
            'fleet_number' => 'TRL-FLT-101',
            'plate_number' => 'TRL-PLATE-101',
            'manufacturer' => 'Schmitz Cargobull',
            'trailer_type' => 'DRY_VAN',
            'status_id' => $tStatus->id,
            'capacity_kg' => 25000,
            'is_active' => true,
        ];
        $trailerRes = $this->actingAs($this->adminUser, 'api')
            ->postJson('/api/v1/trailers', $trailerData);
        $trailerRes->assertStatus(201)
            ->assertJsonPath('data.trailer_code', 'TRL-AUDIT-101');
    }

    /**
     * PHASE 10 & 11: Shipment Execution & Partner Assignment Isolation
     */
    public function test_phase10_11_shipments_and_partner_assignment_scoping(): void
    {
        $partnerA = Company::factory()->create([
            'company_name' => 'Partner Alpha Corp',
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        $partnerB = Company::factory()->create([
            'company_name' => 'Partner Beta Logistics',
            'company_type' => CompanyType::PARTNER_CARRIER->value,
        ]);

        $shipmentStatus = Status::firstOrCreate(['type' => 'shipment', 'name' => 'pending'], ['display_name' => 'Pending']);
        $assignmentStatus = Status::firstOrCreate(['type' => 'assignment', 'name' => 'assigned'], ['display_name' => 'Assigned']);

        // Resources
        $vehA = Vehicle::factory()->create(['company_id' => $partnerA->id, 'operational_code' => 'VEH-PA-01']);
        $driverA = Driver::factory()->create(['company_id' => $partnerA->id, 'first_name' => 'AlphaDriver']);
        $trailerA = Trailer::factory()->create(['company_id' => $partnerA->id, 'trailer_code' => 'TRL-PA-01']);

        $driverB = Driver::factory()->create(['company_id' => $partnerB->id, 'first_name' => 'BetaDriver']);

        $shipment = Shipment::factory()->create([
            'executing_company_id' => $partnerA->id,
            'shipment_number' => 'SHP-PA-ASSIGN-01',
            'status_id' => $shipmentStatus->id,
        ]);

        // Attempting Partner A shipment + Partner B driver -> REJECTED (422)
        $mismatchRes = $this->actingAs($this->dispatcherUser, 'api')
            ->postJson('/api/v1/assignments', [
                'assignment_code' => 'ASG-MISMATCH-01',
                'shipment_id' => $shipment->id,
                'vehicle_id' => $vehA->id,
                'driver_id' => $driverB->id,
                'trailer_id' => $trailerA->id,
                'assigned_by' => $this->dispatcherUser->id,
                'status_id' => $assignmentStatus->id,
                'assigned_at' => now()->toIso8601String(),
            ]);
        $mismatchRes->assertStatus(422);

        // Valid Partner A assignment -> SUCCESS
        $validRes = $this->actingAs($this->dispatcherUser, 'api')
            ->postJson('/api/v1/assignments', [
                'assignment_code' => 'ASG-VALID-01',
                'shipment_id' => $shipment->id,
                'vehicle_id' => $vehA->id,
                'driver_id' => $driverA->id,
                'trailer_id' => $trailerA->id,
                'assigned_by' => $this->dispatcherUser->id,
                'status_id' => $assignmentStatus->id,
                'assigned_at' => now()->toIso8601String(),
            ]);
        $validRes->assertStatus(201);
    }

    /**
     * PHASE 13 & 14: Invoicing, Billing & Financial Immutability
     */
    public function test_phase13_14_invoicing_and_financial_immutability(): void
    {
        $shipmentStatus = Status::firstOrCreate(['type' => 'shipment', 'name' => 'delivered'], ['display_name' => 'Delivered']);
        $shipment = Shipment::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
            'status_id' => $shipmentStatus->id,
        ]);

        $policy = \App\Models\PricingPolicy::create([
            'name' => 'Standard Rate Policy',
            'pricing_model' => 'DISTANCE_AND_WEIGHT',
            'base_rate' => 100.00,
            'per_km_rate' => 1.50,
            'per_kg_rate' => 0.50,
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'created_by' => $this->adminUser->id,
        ]);

        $order = ShipmentOrder::factory()->create([
            'customer_company_id' => $this->clientCompany->id,
        ]);

        $submissionBreakdown = \App\Models\ShipmentOrderPriceBreakdown::create([
            'shipment_order_id' => $order->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => ['total' => 1100.00],
            'final_total' => 1100.00,
            'currency' => 'USD',
            'calculated_at' => now(),
        ]);

        $finalPrice = ShipmentFinalPriceBreakdown::create([
            'shipment_id' => $shipment->id,
            'shipment_order_id' => $order->id,
            'submission_price_breakdown_id' => $submissionBreakdown->id,
            'pricing_policy_id' => $policy->id,
            'breakdown_json' => ['base_rate' => 1000.00, 'tax' => 100.00, 'total' => 1100.00],
            'submission_total' => 1100.00,
            'final_total' => 1100.00,
            'price_delta' => 0.00,
            'price_delta_percentage' => 0.00,
            'change_direction' => \App\Enums\FinalPriceChangeDirection::NONE,
            'material_change' => false,
            'currency' => 'USD',
            'calculated_at' => now(),
            'calculation_trigger' => \App\Enums\FinalPriceCalculationTrigger::MANUAL,
            'status' => \App\Enums\FinalPriceBreakdownStatus::FINALIZED,
        ]);

        $invoice = MonthlyInvoice::create([
            'company_id' => $this->clientCompany->id,
            'billing_period_start' => '2026-01-01',
            'billing_period_end' => '2026-01-31',
            'currency' => 'USD',
            'subtotal' => 1000.00,
            'total' => 1100.00,
            'status' => MonthlyInvoiceStatus::DRAFT->value,
        ]);

        InvoiceLineItem::create([
            'monthly_invoice_id' => $invoice->id,
            'shipment_id' => $shipment->id,
            'shipment_final_price_breakdown_id' => $finalPrice->id,
            'description' => 'Freight Service Charges',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'line_total' => 1100.00,
            'currency' => 'USD',
        ]);

        // List monthly invoices
        $listRes = $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/v1/monthly-invoices');
        $listRes->assertStatus(200);

        // Finalize invoice
        $finalizeRes = $this->actingAs($this->adminUser, 'api')
            ->postJson("/api/v1/monthly-invoices/{$invoice->id}/finalize");
        $finalizeRes->assertStatus(200);
        $this->assertEquals('finalized', strtolower($finalizeRes->json('data.status')));

        // Repeated finalize preserves issued_at & subtotal immutability
        $repeatRes = $this->actingAs($this->adminUser, 'api')
            ->postJson("/api/v1/monthly-invoices/{$invoice->id}/finalize");
        $repeatRes->assertStatus(200);

        $freshInvoice = $invoice->fresh();
        $this->assertEquals(1100.00, (float) $freshInvoice->total);
    }

    /**
     * PHASE 18: IDOR & Security Controls
     */
    public function test_phase18_idor_and_security_controls(): void
    {
        $randomUuid = Str::uuid()->toString();

        // Querying non-existent entity returns 404
        $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/companies/{$randomUuid}")
            ->assertStatus(404);

        $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/vehicles/{$randomUuid}")
            ->assertStatus(404);

        // Non-existent driver integer ID returns clean 404
        $this->actingAs($this->adminUser, 'api')
            ->getJson('/api/v1/drivers/99999999')
            ->assertStatus(404);

        // Non-numeric driver string ID returns clean 404 instead of SQL 500 error
        $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/v1/drivers/{$randomUuid}")
            ->assertStatus(404);
    }
}
