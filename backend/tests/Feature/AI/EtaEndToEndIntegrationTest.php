<?php

namespace Tests\Feature\AI;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AI\Eta\EtaDataQualityMonitor;
use App\Services\AI\Eta\EtaResidualInferenceService;
use App\Services\Eta\FLeetXEnhancedETAService;
use App\Support\AiEtaStatus;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EtaEndToEndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;
    private Company $customerCompany;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.eta.enabled', true);
        $this->seedStatuses();

        $dispatcherRole = Role::firstOrCreate(['name' => 'dispatcher']);
        $driverRole = Role::firstOrCreate(['name' => 'driver']);

        $this->dispatcherUser = User::factory()->create([
            'role_id' => $dispatcherRole->id,
            'password' => Hash::make('password123'),
        ]);

        $driverUser = User::factory()->create(['role_id' => $driverRole->id]);

        $activeCompanyStatus = Status::firstOrCreate(
            ['name' => 'active', 'type' => 'company'],
            ['name' => 'active', 'type' => 'company']
        );

        $this->customerCompany = Company::create([
            'company_code' => 'CMP-E2E-001',
            'company_name' => 'E2E Test Company',
            'legal_name' => 'E2E Test Company Ltd',
            'status_id' => $activeCompanyStatus->id,
            'primary_email' => 'e2e@test.com',
            'primary_phone' => '+963 11 777777',
            'address_line_1' => 'E2E St 10',
            'postal_code' => '10000',
            'city' => 'Damascus',
            'country' => 'SY',
            'currency_code' => 'SYP',
            'is_active' => true,
        ]);

        $driverStatus = Status::firstOrCreate(['name' => 'Available', 'type' => 'driver']);
        Driver::factory()->create([
            'user_id' => $driverUser->id,
            'company_id' => $this->customerCompany->id,
            'years_of_experience' => 12,
            'status_id' => $driverStatus->id,
            'is_active' => true,
        ]);

        Vehicle::factory()->create([
            'company_id' => $this->customerCompany->id,
            'gross_weight_kg' => 22000,
        ]);

        $approvedStatus = Status::where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::APPROVED)->first();
        $inTransitStatus = Status::where('type', ShipmentStatus::TYPE)->where('name', ShipmentStatus::IN_TRANSIT)->first();

        $order = ShipmentOrder::create([
            'request_number' => 'REQ-E2E-' . uniqid(),
            'customer_company_id' => $this->customerCompany->id,
            'submitted_by_user_id' => $this->dispatcherUser->id,
            'status_id' => $approvedStatus->id,
            'reference_number' => 'REF-E2E-01',
            'cargo_type' => 'General Freight',
            'cargo_weight' => 15000.00,
            'pickup_city' => 'Damascus',
            'pickup_country' => 'SY',
            'delivery_city' => 'Latakia',
            'delivery_country' => 'SY',
            'requested_pickup_date' => now()->format('Y-m-d'),
            'requested_delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'planned_duration_seconds' => 10800,
            'planned_distance_meters' => 240000,
        ]);

        $this->shipment = Shipment::create([
            'shipment_number' => 'SHP-E2E-' . rand(10000, 99999),
            'customer_company_id' => $this->customerCompany->id,
            'shipment_order_id' => $order->id,
            'priority' => 'high',
            'status_id' => $inTransitStatus->id,
            'baseline_eta_seconds' => 10800,
            'baseline_distance_meters' => 240000,
            'baseline_routing_provider' => 'graphhopper',
            'baseline_calculated_at' => now()->subMinutes(20),
            'cargo_type' => 'General Freight',
            'cargo_weight' => 15000.00,
            'temperature_controlled' => false,
            'hazardous_cargo' => false,
            'created_by' => $this->dispatcherUser->id,
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

    public function test_end_to_end_ai_eta_prediction_success_path(): void
    {
        /** @var FLeetXEnhancedETAService $etaService */
        $etaService = app(FLeetXEnhancedETAService::class);

        $progress = [
            'remaining_distance_m' => 160000.0,
            'total_distance_m' => 240000.0,
            'distance_travelled_m' => 80000.0,
            'completion_percentage' => 33.33,
        ];
        $currentTracking = [
            'latitude' => 33.53,
            'longitude' => 36.29,
            'speed' => 78.0,
            'heading' => 310.0,
            'accuracy' => 4.0,
        ];

        $payload = $etaService->calculateEta($this->shipment, null, $progress, $currentTracking);

        $this->assertIsArray($payload);
        $this->assertEquals(7200.0, $payload['baseline_remaining_seconds']); // (160km / 80km/h) * 3600 = 7200s
        $this->assertEquals(AiEtaStatus::AI_AVAILABLE, $payload['ai_status']);
        $this->assertEquals(AiEtaStatus::SOURCE_AI_RESIDUAL, $payload['source']);
        $this->assertNotNull($payload['predicted_residual_seconds']);
        $this->assertNotNull($payload['ai_remaining_seconds']);

        // Verify mathematical contract: AI ETA = max(0, baseline + residual)
        $expectedAi = max(0.0, 7200.0 + $payload['predicted_residual_seconds']);
        $this->assertEquals(round($expectedAi, 2), (float) $payload['estimated_remaining_seconds']);
        $this->assertGreaterThan(0.0, $payload['inference_duration_ms']);
    }

    public function test_end_to_end_ai_disabled_config_falls_back_to_baseline(): void
    {
        Config::set('ai.eta.enabled', false);

        /** @var FLeetXEnhancedETAService $etaService */
        $etaService = app(FLeetXEnhancedETAService::class);

        $progress = ['remaining_distance_m' => 160000.0];
        $payload = $etaService->calculateEta($this->shipment, null, $progress);

        $this->assertIsArray($payload);
        $this->assertEquals(7200.0, $payload['baseline_remaining_seconds']);
        $this->assertEquals(7200.0, $payload['estimated_remaining_seconds']);
        $this->assertEquals(AiEtaStatus::AI_UNAVAILABLE, $payload['ai_status']);
        $this->assertEquals(AiEtaStatus::SOURCE_BASELINE_ONLY, $payload['source']);
        $this->assertEquals(0.0, $payload['predicted_residual_seconds']);
    }

    public function test_data_quality_monitor_detects_warnings_without_failing_inference(): void
    {
        $monitor = new EtaDataQualityMonitor();

        $invalidFeatures = [
            'current_latitude' => 120.0, // Invalid latitude
            'current_longitude' => 36.29,
            'current_speed_kmh' => 350.0, // Excessive speed
            'completion_percentage' => 150.0, // Out of bounds
            'remaining_distance_meters' => -50.0,
        ];

        $result = $monitor->validateFeatures($invalidFeatures);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(4, $result['warnings']);
    }

    protected function tearDown(): void
    {
        Config::set('ai.eta.enabled', true);
        parent::tearDown();
    }
}
