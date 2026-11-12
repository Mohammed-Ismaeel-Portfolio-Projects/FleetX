<?php

namespace Database\Factories;

use App\Models\ShipmentOrder;
use App\Models\TrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackingSession>
 */
class TrackingSessionFactory extends Factory
{
    protected $model = TrackingSession::class;

    public function definition(): array
    {
        return [
            'shipment_order_id' => ShipmentOrder::factory(),
            'shipment_id' => null,
            'assignment_id' => null,
            'driver_id' => null,
            'vehicle_id' => null,
            'status' => TrackingSession::STATUS_INACTIVE,
            'speed_multiplier' => 1.00,
            'forced_speed_kmh' => null,
            'forced_deceleration' => false,
            'forced_route_deviation' => false,
            'started_at' => null,
            'paused_at' => null,
            'ended_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrackingSession::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrackingSession::STATUS_PAUSED,
            'started_at' => now()->subMinutes(30),
            'paused_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrackingSession::STATUS_COMPLETED,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);
    }
}
