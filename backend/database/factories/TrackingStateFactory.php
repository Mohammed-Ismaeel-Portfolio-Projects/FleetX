<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\TrackingState;
use App\Services\TrackingSimulator\EuropeanFleetLocations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrackingState>
 */
class TrackingStateFactory extends Factory
{
    protected $model = TrackingState::class;

    public function definition(): array
    {
        $assignment = Assignment::factory()->create();
        $shipment = $assignment->shipment;
        $vehicle = $assignment->vehicle;
        $driver = $assignment->driver;
        $trailer = $assignment->trailer;

        /** @var EuropeanFleetLocations $fleetLocations */
        $fleetLocations = app(EuropeanFleetLocations::class);
        $point = $shipment?->pickup_city
            ? $fleetLocations->pickLogisticsPointForCity($shipment->pickup_city)
            : $fleetLocations->pickDistributedLogisticsPoint();

        return [
            'assignment_id' => $assignment->id,
            'shipment_id' => $shipment->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trailer_id' => $trailer?->id,
            'latitude' => $point['latitude'],
            'longitude' => $point['longitude'],
            'speed' => $this->faker->numberBetween(0, 120),
            'heading' => $this->faker->numberBetween(0, 359),
            'accuracy' => $this->faker->numberBetween(1, 50),
            'movement_status' => $this->faker->randomElement(['Idle', 'Moving', 'Stopped']),
            'online_status' => $this->faker->randomElement(['Online', 'Offline']),
            'signal_quality' => $this->faker->randomElement(['Good', 'Weak', 'Lost']),
            'last_update_at' => now(),
        ];
    }

    public function moving(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'movement_status' => 'Moving',
                'online_status' => 'Online',
                'signal_quality' => 'Good',
                'speed' => $this->faker->numberBetween(30, 120),
            ];
        });
    }

    public function idle(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'movement_status' => 'Idle',
                'speed' => 0,
            ];
        });
    }

    public function offline(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'online_status' => 'Offline',
                'signal_quality' => 'Lost',
            ];
        });
    }
}
