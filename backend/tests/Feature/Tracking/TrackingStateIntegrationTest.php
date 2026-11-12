<?php

namespace Tests\Feature\Tracking;

use App\Models\Assignment;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\TrackingState;
use App\Models\User;
use App\Support\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingStateIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shipment $shipment;
    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        // Truncate to avoid contamination
        \DB::statement('TRUNCATE TABLE tracking_logs CASCADE');
        \DB::statement('TRUNCATE TABLE tracking_states CASCADE');

        $this->user = User::factory()->create();
        $status = Status::firstOrCreate(
            ['name' => ShipmentStatus::DRAFT, 'type' => ShipmentStatus::TYPE],
            ['name' => ShipmentStatus::DRAFT, 'type' => ShipmentStatus::TYPE]
        );
        
        // Create shipment
        $this->shipment = Shipment::create([
            'shipment_number' => 'SHP-STATE-' . now()->timestamp,
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

        // Create assignment for shipment
        $this->assignment = Assignment::factory()->create([
            'shipment_id' => $this->shipment->id,
        ]);
    }

    /**
     * Test that creating a tracking log creates a tracking state.
     */
    public function test_creating_tracking_log_creates_tracking_state(): void
    {
        // Initially no tracking state
        $this->assertDatabaseMissing('tracking_states', [
            'assignment_id' => $this->assignment->id,
        ]);

        // Create tracking log via API
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'speed' => 50,
            'heading' => 90,
            'accuracy' => 5,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201);

        // Verify tracking state was created
        $this->assertDatabaseHas('tracking_states', [
            'assignment_id' => $this->assignment->id,
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'speed' => 50,
        ]);

        // Verify exactly one state exists
        $stateCount = TrackingState::where('assignment_id', $this->assignment->id)->count();
        $this->assertEquals(1, $stateCount, 'Should have exactly one tracking state per assignment');
    }

    /**
     * Test that a second tracking log updates existing state instead of creating another.
     */
    public function test_second_tracking_log_updates_existing_state(): void
    {
        // Create first tracking log
        $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'speed' => 50,
            'heading' => 90,
            'accuracy' => 5,
            'recorded_at' => now()->subMinute()->toDateTimeString(),
        ]);

        // Get the created state
        $firstState = TrackingState::where('assignment_id', $this->assignment->id)->first();
        $this->assertNotNull($firstState);
        $firstId = $firstState->id;

        // Create second tracking log with different location
        $response = $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 41.0000,
            'longitude' => -75.0000,
            'speed' => 75,
            'heading' => 180,
            'accuracy' => 8,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201);

        // Verify still only one state
        $stateCount = TrackingState::where('assignment_id', $this->assignment->id)->count();
        $this->assertEquals(1, $stateCount, 'Should still have exactly one tracking state');

        // Verify it's the same state (by ID)
        $secondState = TrackingState::where('assignment_id', $this->assignment->id)->first();
        $this->assertEquals($firstId, $secondState->id, 'Should be the same state record');

        // Verify state was updated with new values
        $this->assertDatabaseHas('tracking_states', [
            'id' => $firstId,
            'latitude' => 41.0000,
            'longitude' => -75.0000,
            'speed' => 75,
            'heading' => 180,
            'accuracy' => 8,
        ]);
    }

    /**
     * Test that movement status is determined correctly from speed.
     */
    public function test_movement_status_determined_from_speed(): void
    {
        // Test Moving (speed > 5)
        $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'speed' => 50,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('tracking_states', [
            'assignment_id' => $this->assignment->id,
            'movement_status' => 'Moving',
        ]);

        // Update with low speed (0.5 < speed <= 5) = Stopped
        $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'speed' => 2,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('tracking_states', [
            'assignment_id' => $this->assignment->id,
            'movement_status' => 'Stopped',
        ]);

        // Update with zero speed = Idle
        $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'speed' => 0,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('tracking_states', [
            'assignment_id' => $this->assignment->id,
            'movement_status' => 'Idle',
        ]);
    }

    /**
     * Test that signal quality is determined from accuracy.
     */
    public function test_signal_quality_determined_from_accuracy(): void
    {
        // Test Good signal (accuracy <= 10)
        $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 5,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('tracking_states', [
            'assignment_id' => $this->assignment->id,
            'signal_quality' => 'Good',
        ]);

        // Update with weak signal (10 < accuracy <= 30)
        $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 20,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('tracking_states', [
            'assignment_id' => $this->assignment->id,
            'signal_quality' => 'Weak',
        ]);

        // Update with lost signal (accuracy > 30)
        $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 50,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('tracking_states', [
            'assignment_id' => $this->assignment->id,
            'signal_quality' => 'Lost',
        ]);
    }

    /**
     * Test one assignment always has exactly one tracking state.
     */
    public function test_one_assignment_has_exactly_one_tracking_state(): void
    {
        // Create multiple tracking logs rapidly
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', [
                'shipment_id' => $this->shipment->id,
                'latitude' => 40.0 + ($i * 0.1),
                'longitude' => -74.0 + ($i * 0.1),
                'speed' => $i * 5,
                'recorded_at' => now()->addSeconds($i)->toDateTimeString(),
            ]);
        }

        // Verify exactly one state for this assignment
        $stateCount = TrackingState::where('assignment_id', $this->assignment->id)->count();
        $this->assertEquals(1, $stateCount, 'Should have exactly one state after 10 tracking logs');

        // Verify state has latest values
        $state = TrackingState::where('assignment_id', $this->assignment->id)->first();
        $this->assertEqualsWithDelta(40.9, $state->latitude, 0.01, 'Should have latest latitude');
        $this->assertEqualsWithDelta(-73.1, $state->longitude, 0.01, 'Should have latest longitude');
    }

    /**
     * Test that tracking state mirrors all GPS and telemetry data from tracking log.
     */
    public function test_tracking_state_mirrors_all_tracking_log_data(): void
    {
        $trackingData = [
            'shipment_id' => $this->shipment->id,
            'latitude' => 35.6762,
            'longitude' => 139.6503,
            'speed' => 45.5,
            'heading' => 225,
            'accuracy' => 3.2,
            'recorded_at' => now()->toDateTimeString(),
        ];

        $this->actingAs($this->user, 'api')->postJson('/api/v1/tracking-logs', $trackingData);

        $state = TrackingState::where('assignment_id', $this->assignment->id)->first();
        $this->assertNotNull($state);

        // Verify all values are mirrored
        $this->assertEquals((float) $trackingData['latitude'], (float) $state->latitude);
        $this->assertEquals((float) $trackingData['longitude'], (float) $state->longitude);
        $this->assertEquals((float) $trackingData['speed'], (float) $state->speed);
        $this->assertEquals((float) $trackingData['heading'], (float) $state->heading);
        $this->assertEquals((float) $trackingData['accuracy'], (float) $state->accuracy);
    }
}
