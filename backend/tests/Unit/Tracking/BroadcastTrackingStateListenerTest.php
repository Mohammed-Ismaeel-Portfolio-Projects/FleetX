<?php

namespace Tests\Unit\Tracking;

use App\Actions\Tracking\CreateTrackingLogAction;
use App\Actions\Tracking\UpdateTrackingStateAction;
use App\Events\Tracking\TrackingStateBroadcasted;
use App\Events\Tracking\TrackingStateUpdated;
use App\Listeners\Tracking\BroadcastTrackingStateListener;
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
 * Broadcast Tracking State Listener Test
 * 
 * Tests for the broadcast chain:
 * TrackingStateUpdated (domain event) → BroadcastTrackingStateListener → TrackingStateBroadcasted (broadcast event)
 */
class BroadcastTrackingStateListenerTest extends TestCase
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
    public function domain_event_dispatches_after_tracking_state_update()
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

        // Assert: Domain event was dispatched
        Event::assertDispatched(TrackingStateUpdated::class);
    }

    /** @test */
    public function listener_executes_when_domain_event_is_dispatched()
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
        Event::fake([TrackingStateBroadcasted::class]);

        // Act: Update tracking state (this will trigger the listener)
        $this->updateAction->execute($trackingLog);

        // Assert: Broadcast event was dispatched by the listener
        Event::assertDispatched(TrackingStateBroadcasted::class);
    }

    /** @test */
    public function broadcast_event_is_created_with_correct_data()
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
        Event::fake([TrackingStateBroadcasted::class]);

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Broadcast event contains correct data
        Event::assertDispatched(TrackingStateBroadcasted::class, function (TrackingStateBroadcasted $event) use ($vehicle, $shipment, $assignment) {
            return $event->vehicleId === $vehicle->id
                && $event->shipmentId === $shipment->id
                && $event->assignmentId === $assignment->id
                && $event->latitude === 52.5200
                && $event->longitude === 13.4050
                && $event->speed === 60.5
                && $event->heading === 180.0
                && $event->accuracy === 5.0
                && $event->movementStatus === 'Moving'
                && $event->onlineStatus === 'Online';
        });
    }

    /** @test */
    public function broadcast_payload_contains_only_client_required_data()
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
        Event::fake([TrackingStateBroadcasted::class]);

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Broadcast payload is clean and contains only client data
        Event::assertDispatched(TrackingStateBroadcasted::class, function (TrackingStateBroadcasted $event) {
            $payload = $event->broadcastWith();

            // Should contain these fields
            $requiredFields = [
                'vehicle_id',
                'shipment_id',
                'assignment_id',
                'location',
                'telemetry',
                'status',
                'timestamp',
            ];

            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $payload)) {
                    return false;
                }
            }

            // Should NOT contain internal model references
            if (isset($payload['tracking_state']) || isset($payload['trackingState'])) {
                return false;
            }

            // Location should be structured correctly
            if (!isset($payload['location']['latitude']) || !isset($payload['location']['longitude'])) {
                return false;
            }

            // Telemetry should be structured correctly
            if (!isset($payload['telemetry']['heading']) || !isset($payload['telemetry']['speed'])) {
                return false;
            }

            // Status should be structured correctly
            if (!isset($payload['status']['movement_status']) || !isset($payload['status']['online_status'])) {
                return false;
            }

            return true;
        });
    }

    /** @test */
    public function domain_event_remains_independent_from_broadcasting()
    {
        // Assert: Domain event does NOT implement ShouldBroadcast
        $domainEventClass = new \ReflectionClass(TrackingStateUpdated::class);
        
        $this->assertFalse(
            $domainEventClass->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class),
            'TrackingStateUpdated should not implement ShouldBroadcast'
        );

        $this->assertFalse(
            $domainEventClass->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class),
            'TrackingStateUpdated should not implement ShouldBroadcastNow'
        );
    }

    /** @test */
    public function broadcast_event_implements_should_broadcast()
    {
        // Assert: Broadcast event implements ShouldBroadcast
        $broadcastEventClass = new \ReflectionClass(TrackingStateBroadcasted::class);
        
        $this->assertTrue(
            $broadcastEventClass->implementsInterface(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class),
            'TrackingStateBroadcasted should implement ShouldBroadcast'
        );
    }

    /** @test */
    public function broadcast_event_uses_correct_broadcast_name()
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
        Event::fake([TrackingStateBroadcasted::class]);

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Broadcast event uses correct name
        Event::assertDispatched(TrackingStateBroadcasted::class, function (TrackingStateBroadcasted $event) {
            return $event->broadcastAs() === 'tracking.state.updated';
        });
    }

    /** @test */
    public function broadcast_event_broadcasts_to_correct_channels()
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
        Event::fake([TrackingStateBroadcasted::class]);

        // Act: Update tracking state
        $this->updateAction->execute($trackingLog);

        // Assert: Broadcast event uses correct channels
        Event::assertDispatched(TrackingStateBroadcasted::class, function (TrackingStateBroadcasted $event) use ($vehicle, $shipment) {
            $channels = $event->broadcastOn();
            
            // Should have 3 channels: vehicle, shipment, fleet
            $this->assertCount(3, $channels);

            // All channels should be private channels
            foreach ($channels as $channel) {
                $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $channel);
            }

            // Check channel names
            $channelNames = array_map(fn($c) => $c->name, $channels);
            
            $this->assertContains('tracking.vehicle.' . $vehicle->id, $channelNames);
            $this->assertContains('tracking.shipment.' . $shipment->id, $channelNames);
            $this->assertContains('tracking.fleet', $channelNames);

            return true;
        });
    }

    /** @test */
    public function broadcast_event_broadcasts_now()
    {
        // Assert: Broadcast event should broadcast immediately
        $broadcastEventClass = new \ReflectionClass(TrackingStateBroadcasted::class);
        
        $this->assertTrue(
            $broadcastEventClass->hasMethod('broadcastNow'),
            'TrackingStateBroadcasted should have broadcastNow method'
        );
    }

    /** @test */
    public function listener_can_be_called_directly()
    {
        // Arrange: Create domain event
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

        $trackingState = TrackingState::factory()->create([
            'vehicle_id' => $vehicle->id,
            'shipment_id' => $shipment->id,
            'assignment_id' => $assignment->id,
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'speed' => 60.5,
            'heading' => 180.0,
            'accuracy' => 5.0,
            'movement_status' => 'Moving',
            'online_status' => 'Online',
            'signal_quality' => 1,
            'last_update_at' => now(),
        ]);

        $domainEvent = new TrackingStateUpdated($trackingState);

        // Fake the event dispatcher
        Event::fake([TrackingStateBroadcasted::class]);

        // Act: Call listener directly
        $listener = new BroadcastTrackingStateListener();
        $listener->handle($domainEvent);

        // Assert: Broadcast event was dispatched
        Event::assertDispatched(TrackingStateBroadcasted::class);
    }
}
