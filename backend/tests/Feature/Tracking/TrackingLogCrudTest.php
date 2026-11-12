<?php

namespace Tests\Feature\Tracking;

use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\User;
use App\Support\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingLogCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        // Explicitly truncate to avoid contamination
        \DB::statement('TRUNCATE TABLE tracking_logs CASCADE');

        $this->user = User::factory()->create();
        $status = Status::firstOrCreate(
            ['name' => ShipmentStatus::DRAFT, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::DRAFT, 'type' => ShipmentStatus::TYPE]
        );
        $this->shipment = Shipment::create([
            'shipment_number' => 'SHP-10001',
            'customer_company_id' => null,
            'priority' => 'normal',
            'status_id' => $status->id,
            'cargo_type' => 'General Cargo',
            'cargo_description' => 'Test cargo',
            'cargo_weight' => 1000,
            'cargo_volume' => 2,
            'package_count' => 5,
            'hazardous_cargo' => false,
            'temperature_controlled' => false,
            'pickup_company' => 'Origin Co',
            'pickup_contact' => 'Jane',
            'pickup_phone' => '123',
            'pickup_address' => 'Address',
            'pickup_city' => 'City',
            'pickup_country' => 'US',
            'delivery_company' => 'Dest Co',
            'delivery_contact' => 'John',
            'delivery_phone' => '456',
            'delivery_address' => 'Dest Address',
            'delivery_city' => 'City',
            'delivery_country' => 'US',
            'notes' => 'Test',
        ]);
    }

    public function test_can_create_tracking_log(): void
    {
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'speed' => 65.5,
            'heading' => 90,
            'accuracy' => 5,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.shipment_id', $this->shipment->id)
            ->assertJsonPath('data.latitude', 40.7128);

        $this->assertDatabaseHas('tracking_logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => '40.712800',
        ]);
    }

    public function test_can_list_tracking_logs_for_shipment(): void
    {
        // Create a fresh shipment for this test
        $status = Status::where('name', ShipmentStatus::DRAFT)
            ->where('type', ShipmentStatus::TYPE)
            ->first();
        $shipment2 = Shipment::create([
            'shipment_number' => 'SHP-LIST-TEST-' . now()->timestamp,
            'customer_company_id' => null,
            'priority' => 'normal',
            'status_id' => $status->id,
            'cargo_type' => 'General Cargo',
            'cargo_description' => 'Test cargo 2',
            'cargo_weight' => 1000,
            'cargo_volume' => 2,
            'package_count' => 5,
            'hazardous_cargo' => false,
            'temperature_controlled' => false,
            'pickup_company' => 'Origin Co',
            'pickup_contact' => 'Jane',
            'pickup_phone' => '123',
            'pickup_address' => 'Address',
            'pickup_city' => 'City',
            'pickup_country' => 'US',
            'delivery_company' => 'Dest Co',
            'delivery_contact' => 'John',
            'delivery_phone' => '456',
            'delivery_address' => 'Dest Address',
            'delivery_city' => 'City',
            'delivery_country' => 'US',
            'notes' => 'Test 2',
        ]);

        TrackingLog::create([
            'shipment_id' => $shipment2->id,
            'latitude' => 1.1111,
            'longitude' => 2.2222,
            'recorded_at' => now()->subMinute(),
        ]);

        // Debug: Check database count
        $dbCount = TrackingLog::where('shipment_id', $shipment2->id)->count();
        $this->assertEquals(1, $dbCount, "Database should have 1 tracking log for this shipment, found {$dbCount}");

        $response = $this->actingAs($this->user, 'api')->getJson('/api/v1/tracking-logs?shipment_id=' . $shipment2->id);

        $response->assertStatus(200);
        
        // Debug: Log the response
        $jsonCount = count($response->json('data') ?? []);
        $this->assertEquals(1, $jsonCount, "Response should have 1 tracking log, found {$jsonCount}. Full response: " . json_encode($response->json()));
    }

    public function test_can_update_tracking_log(): void
    {
        $trackingLog = TrackingLog::create([
            'shipment_id' => $this->shipment->id,
            'latitude' => 1.1,
            'longitude' => 2.2,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'api')->putJson('/api/v1/tracking-logs/' . $trackingLog->id, [
            'latitude' => 3.3,
            'longitude' => 4.4,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.latitude', 3.3);

        $this->assertDatabaseHas('tracking_logs', ['id' => $trackingLog->id, 'latitude' => '3.300000']);
    }

    public function test_can_delete_tracking_log(): void
    {
        $trackingLog = TrackingLog::create([
            'shipment_id' => $this->shipment->id,
            'latitude' => 1.1,
            'longitude' => 2.2,
            'recorded_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'api')->deleteJson('/api/v1/tracking-logs/' . $trackingLog->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tracking_logs', ['id' => $trackingLog->id]);
    }
}
