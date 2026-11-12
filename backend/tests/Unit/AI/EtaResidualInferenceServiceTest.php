<?php

namespace Tests\Unit\AI;

use App\Models\Assignment;
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
use App\Services\AI\Eta\EtaResidualInferenceService;
use App\Services\Eta\FLeetXEnhancedETAService;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use Tests\TestCase;

class EtaResidualInferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;
    private Company $customerCompany;
    private Shipment $shipment;
    private Driver $driver;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStatuses();

        $dispatcherRole = Role::firstOrCreate(['name' => 'dispatcher']);
        $driverRole = Role::firstOrCreate(['name' => 'driver']);

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'password' => Hash::make('password123'),
        ]);

        $driverUser = User::factory()->create([
            'role_id' => $driverRole->id,
        ]);

        $activeCompanyStatus = Status::firstOrCreate(
            ['name' => 'active', 'type' => 'company'],
            ['name' => 'active', 'type' => 'company']
        );

        $this->customerCompany = Company::create([
            'company_code' => 'CMP-INF-001',
            'company_name' => 'Inference Test Company',
            'legal_name' => 'Inference Test Company Ltd',
            'status_id' => $activeCompanyStatus->id,
            'primary_email' => 'inf@test.com',
            'primary_phone' => '+963 11 888888',
            'address_line_1' => 'Test Ave 2',
            'postal_code' => '10000',
            'city' => 'Damascus',
            'country' => 'SY',
            'currency_code' => 'SYP',
            'is_active' => true,
        ]);

        $driverStatus = Status::firstOrCreate(['name' => 'Available', 'type' => 'driver']);
        $this->driver = Driver::factory()->create([
            'user_id' => $driverUser->id,
            'company_id' => $this->customerCompany->id,
            'years_of_experience' => 10,
            'status_id' => $driverStatus->id,
            'is_active' => true,
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'company_id' => $this->customerCompany->id,
            'gross_weight_kg' => 20000,
        ]);

        $approvedStatus = Status::where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::APPROVED)->first();
        $inTransitStatus = Status::where('type', ShipmentStatus::TYPE)->where('name', ShipmentStatus::IN_TRANSIT)->first();

        $order = ShipmentOrder::create([
            'request_number' => 'REQ-INF-' . uniqid(),
            'customer_company_id' => $this->customerCompany->id,
            'submitted_by_user_id' => $this->dispatcherUser->id,
            'status_id' => $approvedStatus->id,
            'reference_number' => 'REF-INF-01',
            'cargo_type' => 'General Freight',
            'cargo_weight' => 10000.00,
            'pickup_city' => 'Damascus',
            'pickup_country' => 'SY',
            'delivery_city' => 'Aleppo',
            'delivery_country' => 'SY',
            'requested_pickup_date' => now()->format('Y-m-d'),
            'requested_delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'planned_duration_seconds' => 7200,
            'planned_distance_meters' => 150000,
        ]);

        $this->shipment = Shipment::create([
            'shipment_number' => 'SHP-INF-' . rand(10000, 99999),
            'customer_company_id' => $this->customerCompany->id,
            'shipment_order_id' => $order->id,
            'priority' => 'normal',
            'status_id' => $inTransitStatus->id,
            'baseline_eta_seconds' => 7200,
            'baseline_distance_meters' => 150000,
            'baseline_routing_provider' => 'graphhopper',
            'baseline_calculated_at' => now()->subMinutes(10),
            'cargo_type' => 'General Freight',
            'cargo_weight' => 10000.00,
            'temperature_controlled' => false,
            'hazardous_cargo' => false,
            'created_by' => $this->dispatcherUser->id,
        ]);

        $assignmentStatus = Status::firstOrCreate(['name' => 'In Operation', 'type' => 'assignment']);
        Assignment::create([
            'shipment_id' => $this->shipment->id,
            'assignment_code' => 'ASN-' . rand(1000, 9999),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'assigned_by' => $this->dispatcherUser->id,
            'status_id' => $assignmentStatus->id,
            'assigned_at' => now()->subMinutes(30),
            'is_active' => true,
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

        $shpStatuses = [
            ShipmentStatus::APPROVED,
            ShipmentStatus::WAITING_ASSIGNMENT,
            ShipmentStatus::ASSIGNED,
            ShipmentStatus::IN_TRANSIT,
            ShipmentStatus::DELIVERED,
            ShipmentStatus::COMPLETED,
        ];

        foreach ($shpStatuses as $name) {
            Status::firstOrCreate(
                ['name' => $name, 'type' => ShipmentStatus::TYPE],
                ['name' => $name, 'type' => ShipmentStatus::TYPE]
            );
        }
    }

    public function test_feature_builder_constructs_valid_feature_vector_without_leakage(): void
    {
        $builder = new EtaFeatureBuilder();
        $progress = [
            'remaining_distance_m' => 100000.0,
            'total_distance_m' => 150000.0,
            'distance_travelled_m' => 50000.0,
            'completion_percentage' => 33.33,
        ];
        $currentTracking = [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'speed' => 75.0,
            'heading' => 45.0,
            'accuracy' => 4.5,
        ];

        $features = $builder->buildFeatures($this->shipment, $progress, $currentTracking);

        $this->assertIsArray($features);
        $this->assertEquals(7200.0, $features['base_eta_seconds']);
        $this->assertEquals(150000.0, $features['base_distance_meters']);
        $this->assertEquals(100000.0, $features['remaining_distance_meters']);
        $this->assertEquals(50000.0, $features['distance_travelled_meters']);
        $this->assertEquals(33.33, $features['completion_percentage']);
        $this->assertEquals('MOVING', $features['movement_status']);
        $this->assertEquals(10, $features['driver_years_experience']);

        // Zero Leakage Verification
        $this->assertArrayNotHasKey('actual_delivery_at', $features);
        $this->assertArrayNotHasKey('ended_at', $features);
        $this->assertArrayNotHasKey('actual_remaining_seconds', $features);
        $this->assertArrayNotHasKey('residual_seconds', $features);
    }

    public function test_ai_inference_predicts_residual_and_calculates_ai_eta(): void
    {
        /** @var EtaResidualInferenceService $service */
        $service = app(EtaResidualInferenceService::class);

        $progress = [
            'remaining_distance_m' => 100000.0,
            'total_distance_m' => 150000.0,
            'distance_travelled_m' => 50000.0,
            'completion_percentage' => 33.33,
        ];
        $baselineRemainingSeconds = 4500.0; // (100km / 80km/h) * 3600 = 4500s

        $startTime = microtime(true);
        $result = $service->predictResidualEta($this->shipment, $baselineRemainingSeconds, $progress);
        $latencyMs = (microtime(true) - $startTime) * 1000.0;

        $this->assertIsArray($result);
        $this->assertEquals(\App\Support\AiEtaStatus::AI_AVAILABLE, $result['status']);
        $this->assertEquals('ai_residual', $result['source']);
        $this->assertNotNull($result['predicted_residual_seconds']);
        $this->assertNotNull($result['ai_remaining_seconds']);

        // Verify mathematical contract: AI_ETA = baseline + predicted_residual
        $expectedAiEta = max(0.0, $baselineRemainingSeconds + $result['predicted_residual_seconds']);
        $this->assertEquals(round($expectedAiEta, 2), (float) $result['ai_remaining_seconds']);

        // Observability & Latency assertion (< 10000ms for cold process spawn)
        $this->assertLessThan(10000.0, $latencyMs, "Inference latency exceeded threshold: {$latencyMs}ms");
    }

    public function test_negative_residual_clamped_to_non_negative_final_eta(): void
    {
        $mockInferenceService = $this->getMockBuilder(EtaResidualInferenceService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['predictResidualEta'])
            ->getMock();

        $mockInferenceService->method('predictResidualEta')->willReturn([
            'status' => \App\Support\AiEtaStatus::AI_AVAILABLE,
            'source' => 'ai_residual',
            'predicted_residual_seconds' => -99999.0, // Extreme negative residual
            'ai_remaining_seconds' => 0.0, // Safely clamped to 0.0
            'model_version' => 'v1.0',
            'inference_duration_ms' => 12.5,
        ]);

        $result = $mockInferenceService->predictResidualEta($this->shipment, 3600.0, []);
        $this->assertGreaterThanOrEqual(0.0, $result['ai_remaining_seconds']);
    }

    public function test_missing_model_artifact_falls_back_to_baseline(): void
    {
        $mockLoader = $this->createMock(EtaModelArtifactLoader::class);
        $mockLoader->method('isArtifactReady')->willReturn(false);
        $mockLoader->method('getModelVersion')->willReturn('v1.0');

        $featureBuilder = new EtaFeatureBuilder();
        $qualityMonitor = new \App\Services\AI\Eta\EtaDataQualityMonitor();
        $service = new EtaResidualInferenceService($featureBuilder, $mockLoader, $qualityMonitor);

        $result = $service->predictResidualEta($this->shipment, 3600.0, []);

        $this->assertEquals(\App\Support\AiEtaStatus::AI_UNAVAILABLE, $result['status']);
        $this->assertEquals('baseline_only', $result['source']);
        $this->assertEquals(3600.0, $result['ai_remaining_seconds']);
        $this->assertEquals(0.0, $result['predicted_residual_seconds']);
    }

    public function test_fleetx_enhanced_eta_service_integrates_ai_correction_with_baseline_fallback(): void
    {
        /** @var FLeetXEnhancedETAService $service */
        $service = app(FLeetXEnhancedETAService::class);

        $progress = ['remaining_distance_m' => 80000.0]; // 80km at 80km/h = 3600s
        $result = $service->calculateEta($this->shipment, null, $progress);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('estimated_remaining_seconds', $result);
        $this->assertArrayHasKey('baseline_remaining_seconds', $result);
        $this->assertArrayHasKey('predicted_residual_seconds', $result);
        $this->assertArrayHasKey('ai_remaining_seconds', $result);
        $this->assertArrayHasKey('source', $result);
        $this->assertArrayHasKey('ai_status', $result);

        $this->assertEquals(3600.0, $result['baseline_remaining_seconds']);
        $this->assertGreaterThanOrEqual(0.0, $result['estimated_remaining_seconds']);
    }
}
