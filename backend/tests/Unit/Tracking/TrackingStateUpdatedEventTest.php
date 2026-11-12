<?php

namespace Tests\Unit\Tracking;

use App\Actions\Tracking\CreateTrackingLogAction;
use App\Actions\Tracking\UpdateTrackingStateAction;
use App\Events\Tracking\TrackingStateUpdated;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\TrackingState;
use App\Models\Trailer;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tracking State Updated Event Test
 * 
 * Tests for the TrackingStateUpdated domain event.
 * Verifies that the event is dispatched correctly after tracking state updates.
 */
class TrackingStateUpdatedEventTest extends TestCase
{
    use RefreshDatabase;

    protected UpdateTrackingStateAction $updateAction;
    protected CreateTrackingLogAction $createLogAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->updateAction = app(UpdateTrackingStateAction::class);
        $this->createLogAction = app(CreateTrackingLogAction::class);
    }

    /** @test */
    public function event_is_dispatched_after_successful_tracking_state_update()
    {
        // Arrange: Create test data
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $trailer = Trailer::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'shipment_id' => $shipment->id,
            'status' => 'active',
        ]);

        // Create a tracking log
        $logResult = $this->createLogAction->execute([
            'shipment_id' => $shipment->id,
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'speed' => 60.5,
            'heading' => 180.0,
            'accuracy' => 5.0,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $this->assertTrue($logResult->success);
        $trackingLog = TrackingLog::find($logResult->data->id);

        // Fake the event dispatcher
        Event::fake();

        // Act: Update tracking state
        $result = $this->updateAction->execute($trackingLog);

        // Assert: Event was dispatched
        Event::assertDispatched(TrackingStateUpdated::class, function (TrackingStateUpdated $event) use ($vehicle, $shipment) {
            return $event->vehicleId === $vehicle->id
                && $event->shipmentId === $shipment->id
                && $event->latitude === 52.5200
                && $event->longitude === 13.4050
                && $event->speed === 60.5
                && $event->heading === 180.0;
        });

        $this->assertTrue($result->success);
    }

    /** @test */
    public function event_is_not_dispatched_when_persistence_fails()
    {
        // Arrange: Create test data with invalid shipment (will cause failure)
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $trailer = Trailer::factory()->create();
        
        // Create a tracking log without a valid shipment
        $trackingLog = TrackingLog::factory()->create([
            'shipment_id' => null, // This will cause the action to fail
            'latitude' => 52.5200,
            'longitude' => 13.4050,
        ]);

        // Fake the event dispatcher
        Event::fake();

        // Act: Attempt to update tracking state
        $result = $this->updateAction->execute($trackingLog);

        // Assert: Event was NOT dispatched
        Event::assertNotDispatched(TrackingStateUpdated::class);
        $this->assertFalse($result->success);
    }

    /** @test */
    public function event_payload_contains_expected_identifiers()
    {
        // Arrange: Create test data
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $trailer = Trailer::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'shipment_id' => $shipment->id,
            'status' => 'active',
        ]);

        $logResult = $this->createLogAction->execute([
            'shipment_id' => $shipment->id,
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'speed' => 60.5,
            'heading' => 180.0,
            'accuracy' => 5.0,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $trackingLog = TrackingLog::find($logResult->data->id);

        // Fake the event dispatcher
        Event::fake();

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Event payload contains expected identifiers
        Event::assertDispatched(TrackingStateUpdated::class, function (TrackingStateUpdated $event) use ($vehicle, $shipment, $assignment) {
            $array = $event->toArray();

            return isset($array['tracking_state_id'])
                && isset($array['vehicle_id'])
                && isset($array['shipment_id'])
                && isset($array['assignment_id'])
                && $array['vehicle_id'] === $vehicle->id
                && $array['shipment_id'] === $shipment->id
                && $array['assignment_id'] === $assignment->id
                && $array['location']['latitude'] === 52.5200
                && $array['location']['longitude'] === 13.4050
                && $array['telemetry']['speed'] === 60.5
                && $array['telemetry']['heading'] === 180.0
                && isset($array['status']['movement_status'])
                && isset($array['status']['online_status']);
        });
    }

    /** @test */
    public function event_does_not_implement_should_broadcast()
    {
        // Assert: The event class does not implement ShouldBroadcast
        $eventClass = new \ReflectionClass(TrackingStateUpdated::class);
        
        $this->assertFalse(
            $eventClass->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class),
            'TrackingStateUpdated should not implement ShouldBroadcast'
        );

        $this->assertFalse(
            $eventClass->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class),
            'TrackingStateUpdated should not implement ShouldBroadcastNow'
        );
    }

    /** @test */
    public function event_contains_correct_telemetry_data()
    {
        // Arrange: Create test data
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $trailer = Trailer::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'shipment_id' => $shipment->id,
            'status' => 'active',
        ]);

        $logResult = $this->createLogAction->execute([
            'shipment_id' => $shipment->id,
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'speed' => 60.5,
            'heading' => 180.0,
            'accuracy' => 5.0,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $trackingLog = TrackingLog::find($logResult->data->id);

        // Fake the event dispatcher
        Event::fake();

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Event contains correct telemetry
        Event::assertDispatched(TrackingStateUpdated::class, function (TrackingStateUpdated $event) {
            $telemetry = $event->getTelemetry();
            
            return $telemetry['speed'] === 60.5
                && $telemetry['heading'] === 180.0
                && $telemetry['accuracy'] === 5.0
                && isset($telemetry['signal_quality']);
        });
    }

    /** @test */
    public function event_contains_correct_status_data()
    {
        // Arrange: Create test data
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $trailer = Trailer::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'shipment_id' => $shipment->id,
            'status' => 'active',
        ]);

        $logResult = $this->createLogAction->execute([
            'shipment_id' => $shipment->id,
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'speed' => 60.5,
            'heading' => 180.0,
            'accuracy' => 5.0,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $trackingLog = TrackingLog::find($logResult->data->id);

        // Fake the event dispatcher
        Event::fake();

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Event contains correct status
        Event::assertDispatched(TrackingStateUpdated::class, function (TrackingStateUpdated $event) {
            $status = $event->getStatus();
            
            return isset($status['movement_status'])
                && isset($status['online_status'])
                && $status['online_status'] === 'Online'; // Should be Online after GPS update
        });
    }

    /** @test */
    public function event_has_correct_event_type()
    {
        // Arrange: Create test data
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $trailer = Trailer::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'shipment_id' => $shipment->id,
            'status' => 'active',
        ]);

        $logResult = $this->createLogAction->execute([
            'shipment_id' => $shipment->id,
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'speed' => 60.5,
            'heading' => 180.0,
            'accuracy' => 5.0,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $trackingLog = TrackingLog::find($logResult->data->id);

        // Fake the event dispatcher
        Event::fake();

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Event has correct type
        Event::assertDispatched(TrackingStateUpdated::class, function (TrackingStateUpdated $event) {
            return $event->getEventType() === 'tracking.state.updated';
        });
    }

    /** @test */
    public function event_has_occurred_at_timestamp()
    {
        // Arrange: Create test data
        $vehicle = Vehicle::factory()->create();
        $driver = Driver::factory()->create();
        $trailer = Trailer::factory()->create();
        $shipment = Shipment::factory()->create();
        $assignment = Assignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer->id,
            'shipment_id' => $shipment->id,
            'status' => 'active',
        ]);

        $logResult = $this->createLogAction->execute([
            'shipment_id' => $shipment->id,
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'speed' => 60.5,
            'heading' => 180.0,
            'accuracy' => 5.0,
            'recorded_at' => now()->toDateTimeString(),
        ]);

        $trackingLog = TrackingLog::find($logResult->data->id);

        // Fake the event dispatcher
        Event::fake();

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Event has timestamp
        Event::assertDispatched(TrackingStateUpdated::class, function (TrackingStateUpdated $event) {
            $array = $event->toArray();
            
            return isset($array['occurred_at'])
                && $event->occurredAt instanceof \DateTimeImmutable;
        });
    }
}
