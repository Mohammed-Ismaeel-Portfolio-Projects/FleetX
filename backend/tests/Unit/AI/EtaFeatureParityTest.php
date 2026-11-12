<?php

namespace Tests\Unit\AI;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AI\Eta\EtaFeatureBuilder;
use App\Services\AI\Eta\EtaModelArtifactLoader;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EtaFeatureParityTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStatuses();

        $dispatcherRole = Role::firstOrCreate(['name' => 'dispatcher']);
        $driverRole = Role::firstOrCreate(['name' => 'driver']);

        $dispatcher = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'password' => Hash::make('password123'),
        ]);

        $driverUser = User::factory()->create(['role_id' => $driverRole->id]);

        $activeCompanyStatus = Status::firstOrCreate(
            ['name' => 'active', 'type' => 'company'],
            ['name' => 'active', 'type' => 'company']
        );

        $company = Company::create([
            'company_code' => 'CMP-PAR-001',
            'company_name' => 'Parity Test Company',
            'legal_name' => 'Parity Test Company Ltd',
            'status_id' => $activeCompanyStatus->id,
            'primary_email' => 'par@test.com',
            'primary_phone' => '+963 11 999999',
            'address_line_1' => 'Parity Ave 1',
            'postal_code' => '10000',
            'city' => 'Damascus',
            'country' => 'SY',
            'currency_code' => 'SYP',
            'is_active' => true,
        ]);

        $driverStatus = Status::firstOrCreate(['name' => 'Available', 'type' => 'driver']);
        Driver::factory()->create([
            'user_id' => $driverUser->id,
            'company_id' => $company->id,
            'years_of_experience' => 8,
            'status_id' => $driverStatus->id,
            'is_active' => true,
        ]);

        Vehicle::factory()->create([
            'company_id' => $company->id,
            'gross_weight_kg' => 19000,
        ]);

        $approvedStatus = Status::where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::APPROVED)->first();
        $inTransitStatus = Status::where('type', ShipmentStatus::TYPE)->where('name', ShipmentStatus::IN_TRANSIT)->first();

        $order = ShipmentOrder::create([
            'request_number' => 'REQ-PAR-' . uniqid(),
            'customer_company_id' => $company->id,
            'submitted_by_user_id' => $dispatcher->id,
            'status_id' => $approvedStatus->id,
            'reference_number' => 'REF-PAR-01',
            'cargo_type' => 'Refrigerated Food',
            'cargo_weight' => 12000.00,
            'pickup_city' => 'Damascus',
            'pickup_country' => 'SY',
            'delivery_city' => 'Homs',
            'delivery_country' => 'SY',
            'requested_pickup_date' => now()->format('Y-m-d'),
            'requested_delivery_date' => now()->addDays(1)->format('Y-m-d'),
            'planned_duration_seconds' => 5400,
            'planned_distance_meters' => 120000,
        ]);

        $this->shipment = Shipment::create([
            'shipment_number' => 'SHP-PAR-' . rand(10000, 99999),
            'customer_company_id' => $company->id,
            'shipment_order_id' => $order->id,
            'priority' => 'high',
            'status_id' => $inTransitStatus->id,
            'baseline_eta_seconds' => 5400,
            'baseline_distance_meters' => 120000,
            'baseline_routing_provider' => 'graphhopper',
            'baseline_calculated_at' => now()->subMinutes(15),
            'cargo_type' => 'Refrigerated Food',
            'cargo_weight' => 12000.00,
            'temperature_controlled' => true,
            'hazardous_cargo' => false,
            'created_by' => $dispatcher->id,
        ]);
    }

    private function seedStatuses(): void
    {
        foreach (ShipmentOrderStatus::all() as $name) {
            Status::firstOrCreate(
                ['name' => $name, 'type' => ShipmentOrderStatus::TYPE],
                ['name' => $name, 'type' => ShipmentOrderStatus::TYPE]
            );
        }

        foreach ([ShipmentStatus::APPROVED, ShipmentStatus::IN_TRANSIT, ShipmentStatus::DELIVERED] as $name) {
            Status::firstOrCreate(
                ['name' => $name, 'type' => ShipmentStatus::TYPE],
                ['name' => $name, 'type' => ShipmentStatus::TYPE]
            );
        }
    }

    public function test_online_feature_vector_matches_feature_manifest_contract_exactly(): void
    {
        $loader = new EtaModelArtifactLoader();
        $manifest = $loader->getManifest();

        $this->assertNotNull($manifest, 'Feature manifest file feature_manifest.json is missing or corrupted!');

        $requiredFeatures = $manifest['feature_names'];
        $excludedLeakageCols = $manifest['excluded_leakage_cols'];

        $builder = new EtaFeatureBuilder();
        $progress = [
            'remaining_distance_m' => 90000.0,
            'total_distance_m' => 120000.0,
            'distance_travelled_m' => 30000.0,
            'completion_percentage' => 25.0,
        ];
        $tracking = [
            'latitude' => 33.52,
            'longitude' => 36.28,
            'speed' => 80.0,
            'heading' => 90.0,
            'accuracy' => 3.0,
        ];

        $onlineFeatures = $builder->buildFeatures($this->shipment, $progress, $tracking);

        // 1. Verify exact feature key match
        foreach ($requiredFeatures as $expectedKey) {
            $this->assertArrayHasKey(
                $expectedKey,
                $onlineFeatures,
                "Feature Contract Defect: Required online feature '{$expectedKey}' missing from feature builder payload!"
            );
        }

        $this->assertCount(count($requiredFeatures), $onlineFeatures);

        // 2. Strict Data Leakage Verification
        foreach ($excludedLeakageCols as $leakageKey) {
            $this->assertArrayNotHasKey(
                $leakageKey,
                $onlineFeatures,
                "Data Leakage Violation: Forbidden feature '{$leakageKey}' found in online feature builder payload!"
            );
        }

        // 3. Types and representation check
        $this->assertIsFloat($onlineFeatures['base_eta_seconds']);
        $this->assertIsFloat($onlineFeatures['remaining_distance_meters']);
        $this->assertIsString($onlineFeatures['movement_status']);
        $this->assertContains($onlineFeatures['movement_status'], ['MOVING', 'SLOW', 'STOPPED']);
        $this->assertIsInt($onlineFeatures['temperature_controlled']);
        $this->assertContains($onlineFeatures['temperature_controlled'], [0, 1]);
        $this->assertIsInt($onlineFeatures['hour_of_day']);
        $this->assertGreaterThanOrEqual(0, $onlineFeatures['hour_of_day']);
        $this->assertLessThanOrEqual(23, $onlineFeatures['hour_of_day']);
    }
}
