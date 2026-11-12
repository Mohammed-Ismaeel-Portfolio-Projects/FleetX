<?php

namespace Tests\Unit\AI;

use App\Actions\AI\ExtractEtaTrainingDataAction;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Models\EtaObservationDatasetSample;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExtractEtaTrainingDataActionTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcherUser;
    private Company $customerCompany;
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
            'company_code' => 'CMP-AI-001',
            'company_name' => 'AI Test Company',
            'legal_name' => 'AI Test Company Ltd',
            'status_id' => $activeCompanyStatus->id,
            'primary_email' => 'ai@test.com',
            'primary_phone' => '+963 11 999999',
            'address_line_1' => 'Test Ave 1',
            'postal_code' => '10000',
            'city' => 'Damascus',
            'country' => 'SY',
            'currency_code' => 'SYP',
            'is_active' => true,
        ]);

        $driverStatus = Status::firstOrCreate(
            ['name' => 'Available', 'type' => 'driver'],
            ['name' => 'Available', 'type' => 'driver']
        );

        $this->driver = Driver::factory()->create([
            'user_id' => $driverUser->id,
            'company_id' => $this->customerCompany->id,
            'years_of_experience' => 7,
            'status_id' => $driverStatus->id,
            'is_active' => true,
        ]);

        DriverScore::create([
            'driver_id' => $this->driver->id,
            'score' => 92.50,
            'total_deliveries' => 45,
            'evaluated_assignments_count' => 40,
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'company_id' => $this->customerCompany->id,
            'gross_weight_kg' => 18000,
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

        Status::firstOrCreate(
            ['name' => 'In Operation', 'type' => 'assignment'],
            ['name' => 'In Operation', 'type' => 'assignment']
        );
        Status::firstOrCreate(
            ['name' => 'Completed', 'type' => 'assignment'],
            ['name' => 'Completed', 'type' => 'assignment']
        );
    }

    private function createCompletedShipmentWithLogs(): Shipment
    {
        $approvedStatus = Status::where('type', ShipmentOrderStatus::TYPE)->where('name', ShipmentOrderStatus::APPROVED)->firstOrFail();
        $completedStatus = Status::where('type', ShipmentStatus::TYPE)->where('name', ShipmentStatus::COMPLETED)->firstOrFail();

        $order = ShipmentOrder::create([
            'request_number' => 'REQ-AI-' . uniqid(),
            'customer_company_id' => $this->customerCompany->id,
            'submitted_by_user_id' => $this->dispatcherUser->id,
            'status_id' => $approvedStatus->id,
            'reference_number' => 'REF-AI-01',
            'cargo_type' => 'Refrigerated Food',
            'cargo_weight' => 8500.00,
            'pickup_city' => 'Damascus',
            'pickup_country' => 'SY',
            'pickup_latitude' => 33.5138,
            'pickup_longitude' => 36.2765,
            'delivery_city' => 'Aleppo',
            'delivery_country' => 'SY',
            'delivery_latitude' => 36.2021,
            'delivery_longitude' => 37.1343,
            'requested_pickup_date' => now()->subHours(5)->format('Y-m-d'),
            'requested_delivery_date' => now()->format('Y-m-d'),
            'planned_duration_seconds' => 7200,
            'planned_distance_meters' => 150000,
            'planned_route_geometry' => [
                [33.5138, 36.2765],
                [34.8000, 36.7000],
                [36.2021, 37.1343],
            ],
            'planned_route_status' => 'AVAILABLE',
            'planned_route_calculated_at' => now()->subHours(6),
        ]);

        $pickupAt = now()->subHours(4);
        $deliveryAt = now()->subHours(1);

        $shipment = Shipment::create([
            'shipment_number' => 'SHP-AI-' . rand(10000, 99999),
            'customer_company_id' => $this->customerCompany->id,
            'shipment_order_id' => $order->id,
            'priority' => 'normal',
            'status_id' => $completedStatus->id,
            'baseline_eta_seconds' => 7200,
            'baseline_distance_meters' => 150000,
            'baseline_routing_provider' => 'graphhopper',
            'baseline_calculated_at' => now()->subHours(6),
            'cargo_type' => 'Refrigerated Food',
            'cargo_weight' => 8500.00,
            'temperature_controlled' => true,
            'hazardous_cargo' => false,
            'actual_pickup_at' => $pickupAt,
            'actual_delivery_at' => $deliveryAt,
            'created_by' => $this->dispatcherUser->id,
        ]);

        $assignmentStatus = Status::where('type', 'assignment')->where('name', 'Completed')->firstOrFail();
        Assignment::create([
            'shipment_id' => $shipment->id,
            'assignment_code' => 'ASN-' . rand(1000, 9999),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'assigned_by' => $this->dispatcherUser->id,
            'status_id' => $assignmentStatus->id,
            'assigned_at' => now()->subHours(5),
            'ended_at' => $deliveryAt,
            'is_active' => false,
        ]);

        // Historical GPS Observations (T1 = 3h before delivery, T2 = 2h before delivery)
        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'speed' => 65.0,
            'heading' => 45.0,
            'accuracy' => 5.0,
            'recorded_at' => now()->subHours(3),
        ]);

        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 34.8000,
            'longitude' => 36.7000,
            'speed' => 80.0,
            'heading' => 30.0,
            'accuracy' => 4.0,
            'recorded_at' => now()->subHours(2),
        ]);

        return $shipment;
    }

    public function test_valid_completed_shipment_extracts_observations(): void
    {
        $shipment = $this->createCompletedShipmentWithLogs();

        /** @var ExtractEtaTrainingDataAction $action */
        $action = app(ExtractEtaTrainingDataAction::class);
        $result = $action->execute($shipment->id);

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->data['shipments_processed']);
        $this->assertEquals(2, $result->data['samples_extracted']);

        $samples = EtaObservationDatasetSample::where('shipment_id', $shipment->id)->get();
        $this->assertCount(2, $samples);
    }

    public function test_actual_remaining_and_residual_seconds_calculated_correctly(): void
    {
        $shipment = $this->createCompletedShipmentWithLogs();

        $action = app(ExtractEtaTrainingDataAction::class);
        $action->execute($shipment->id);

        /** @var EtaObservationDatasetSample $sample */
        $sample = EtaObservationDatasetSample::where('shipment_id', $shipment->id)
            ->orderBy('observation_time', 'asc')
            ->first();

        $this->assertNotNull($sample);

        // T1 recorded 2 hours (7200 seconds) before actual_delivery_at
        $expectedRemaining = (float) ($shipment->actual_delivery_at->timestamp - $sample->observation_time->timestamp);
        $this->assertEquals($expectedRemaining, (float) $sample->actual_remaining_seconds);

        // Verify baseline_remaining_seconds: (remaining_distance_meters / 1000) / 80.0 * 3600
        $expectedBaselineRemaining = ((float) $sample->remaining_distance_meters / 1000.0) / 80.0 * 3600.0;
        $this->assertEquals(round($expectedBaselineRemaining, 2), (float) $sample->baseline_remaining_seconds);

        // Verify residual_seconds = actual_remaining_seconds - baseline_remaining_seconds
        $expectedResidual = (float) $sample->actual_remaining_seconds - (float) $sample->baseline_remaining_seconds;
        $this->assertEquals(round($expectedResidual, 2), (float) $sample->residual_seconds);
    }

    public function test_observations_at_or_after_actual_delivery_at_are_rejected(): void
    {
        $shipment = $this->createCompletedShipmentWithLogs();

        // Create future / post-delivery observation
        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => 36.2021,
            'longitude' => 37.1343,
            'speed' => 0.0,
            'heading' => 0.0,
            'recorded_at' => $shipment->actual_delivery_at->addMinutes(5),
        ]);

        $action = app(ExtractEtaTrainingDataAction::class);
        $result = $action->execute($shipment->id);

        $this->assertTrue($result->success);
        // Only 2 valid pre-delivery observations should be extracted
        $this->assertEquals(2, $result->data['samples_extracted']);
    }

    public function test_actual_delivery_at_is_never_persisted_as_an_input_feature(): void
    {
        $shipment = $this->createCompletedShipmentWithLogs();

        $action = app(ExtractEtaTrainingDataAction::class);
        $action->execute($shipment->id);

        $sample = EtaObservationDatasetSample::where('shipment_id', $shipment->id)->first();
        $this->assertNotNull($sample);

        $attributes = $sample->getAttributes();
        $this->assertArrayNotHasKey('actual_delivery_at', $attributes);
        $this->assertArrayNotHasKey('ended_at', $attributes);
    }

    public function test_missing_actual_delivery_at_excludes_shipment(): void
    {
        $shipment = $this->createCompletedShipmentWithLogs();
        $shipment->update(['actual_delivery_at' => null]);

        $action = app(ExtractEtaTrainingDataAction::class);
        $result = $action->execute($shipment->id);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->data['samples_extracted']);
    }

    public function test_missing_baseline_eta_data_excludes_shipment(): void
    {
        $shipment = $this->createCompletedShipmentWithLogs();
        $shipment->update([
            'baseline_eta_seconds' => null,
            'baseline_distance_meters' => null,
        ]);
        $shipment->shipmentOrder?->update([
            'planned_duration_seconds' => null,
            'planned_distance_meters' => null,
        ]);

        $action = app(ExtractEtaTrainingDataAction::class);
        $result = $action->execute($shipment->id);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->data['samples_extracted']);
    }

    public function test_extraction_is_idempotent_and_prevents_duplicate_samples(): void
    {
        $shipment = $this->createCompletedShipmentWithLogs();

        $action = app(ExtractEtaTrainingDataAction::class);
        $firstRun = $action->execute($shipment->id);
        $this->assertEquals(2, $firstRun->data['samples_extracted']);

        // Rerun extraction
        $secondRun = $action->execute($shipment->id);
        $this->assertEquals(2, $secondRun->data['samples_extracted']);

        // Total count in database remains exactly 2
        $count = EtaObservationDatasetSample::where('shipment_id', $shipment->id)->count();
        $this->assertEquals(2, $count);
    }
}
